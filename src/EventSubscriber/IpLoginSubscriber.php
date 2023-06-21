<?php

namespace Drupal\ip_login\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * IP Login subscriber.
 */
class IpLoginSubscriber implements EventSubscriberInterface {

  /**
   * Clears various IP Login cookies if needed.
   *
   * @param \Symfony\Component\HttpKernel\Event\ResponseEvent $event
   *   The response event.
   */
  public function onKernelResponse(ResponseEvent $event) {
    if (!$event->isMainRequest()) {
      return;
    }

    $response = $event->getResponse();
    if ($event->getRequest()->attributes->get('ip_login_user_login')) {
      $response->headers->setCookie(Cookie::create('ipLoginAttempted', '', 1));
      $response->headers->setCookie(Cookie::create('ipLoginAsDifferentUser', '', 1));
    }

    $can_login_as_another_user = $event->getRequest()->attributes->get('ip_login_can_login_as_another_user');
    if ($can_login_as_another_user !== NULL) {
      $response->headers->setCookie(Cookie::create('ipLoginAsDifferentUser', $can_login_as_another_user));
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[KernelEvents::RESPONSE][] = ['onKernelResponse', 0];

    return $events;
  }

}
