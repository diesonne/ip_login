<?php

namespace Drupal\ip_login\StackMiddleware;

use Drupal\ip_login\IpLoginController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\HttpKernelInterface;

/**
 * Provides a HTTP middleware to implement IP based login.
 */
class IpLoginMiddleware implements HttpKernelInterface {

  /**
   * The decorated kernel.
   *
   * @var \Symfony\Component\HttpKernel\HttpKernelInterface
   */
  protected $httpKernel;

  /**
   * Constructs an IpLoginMiddleware.
   *
   * @param \Symfony\Component\HttpKernel\HttpKernelInterface $http_kernel
   *   The decorated kernel.
   */
  public function __construct(HttpKernelInterface $http_kernel) {
    $this->httpKernel = $http_kernel;
  }

  /**
   * {@inheritdoc}
   */
  public function handle(Request $request, $type = self::MASTER_REQUEST, $catch = TRUE) {
    if ($ip_login_uid = $request->attributes->get('ip_login_uid')) {
      $request->attributes->remove('ip_login_uid');
      IpLoginController::doUserLogin($ip_login_uid, $request);
    }

    return $this->httpKernel->handle($request, $type, $catch);
  }

}
