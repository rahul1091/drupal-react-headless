<?php

namespace Drupal\apiservices\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Makes the Drupal session cookie usable from a cross-origin SPA.
 *
 * The React app can run on a different origin than Drupal (e.g. the
 * standalone Vite dev server at http://localhost:5173, talking to
 * http://dr-headless.ddev.site). Axios sends the request with
 * `withCredentials: true`, and CORS is configured to allow credentials -
 * but neither of those matters if the session cookie itself can't survive
 * a cross-site request.
 *
 * By default Drupal's session cookie has no explicit SameSite attribute,
 * so modern browsers treat it as SameSite=Lax. A Lax cookie CAN be set by
 * a cross-site response (which is why login appears to "succeed"), but it
 * will NOT be sent back on subsequent cross-site XHR/fetch requests - only
 * on top-level navigations. That's exactly the symptom this fixes: every
 * endpoint that checks `currentUser->isAnonymous()` (task-list, user-list,
 * add-task) sees an anonymous session even though the SPA just logged in.
 *
 * The fix is to mark the cookie SameSite=None so cross-site XHR/fetch can
 * carry it - but browsers require the Secure flag alongside SameSite=None,
 * which in turn requires HTTPS. So this only takes effect on secure
 * requests; see the README for why the dev-server flow needs an https://
 * Drupal URL, not http://.
 */
class CrossOriginSessionCookieSubscriber implements EventSubscriberInterface
{

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents()
  {
    // Run after the session is written to the response (default session
    // listener priority is 0), so the cookie we want to rewrite already
    // exists on the response by the time we get here.
    return [KernelEvents::RESPONSE => ['onKernelResponse', -10]];
  }

  /**
   * Rewrites the session cookie's SameSite/Secure attributes.
   *
   * @param \Symfony\Component\HttpKernel\Event\ResponseEvent $event
   *   The response event.
   */
  public function onKernelResponse(ResponseEvent $event)
  {
    $request = $event->getRequest();
    $path = $request->getPathInfo();

    // Only touch responses on routes the SPA actually calls cross-origin.
    // Never rewrite cookies on admin pages, node forms, etc.
    $isSpaRoute = str_starts_with($path, '/api/')
      || str_starts_with($path, '/user/login')
      || str_starts_with($path, '/user/logout')
      || str_starts_with($path, '/session/token');

    if (!$isSpaRoute) {
      return;
    }

    // SameSite=None cookies are only honored by browsers when Secure is
    // also set, which in turn only works over HTTPS. If this request came
    // in over plain HTTP, there is nothing safe to do here - rewriting the
    // cookie without Secure would just be silently dropped by the browser
    // anyway, so leave it as the default Lax cookie rather than pretend.
    if (!$request->isSecure()) {
      return;
    }

    $response = $event->getResponse();
    $headers = $response->headers;

    foreach ($headers->getCookies() as $cookie) {
      // Drupal's session cookie is named SESS... (http) or SSESS... (https).
      // Leave every other cookie (if any) untouched.
      if (!str_starts_with($cookie->getName(), 'SESS') && !str_starts_with($cookie->getName(), 'SSESS')) {
        continue;
      }

      $headers->removeCookie($cookie->getName(), $cookie->getPath(), $cookie->getDomain());
      $headers->setCookie(
        Cookie::create(
          $cookie->getName(),
          $cookie->getValue(),
          $cookie->getExpiresTime(),
          $cookie->getPath(),
          $cookie->getDomain(),
          TRUE, // Secure - required for SameSite=None to be honored.
          $cookie->isHttpOnly(),
          $cookie->isRaw(),
          Cookie::SAMESITE_NONE
        )
      );
    }
  }
}
