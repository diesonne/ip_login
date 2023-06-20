<?php

namespace Drupal\ip_login\Form;

use Drupal\Core\Cache\CacheFactoryInterface;
use Drupal\Core\Cache\CacheTagsInvalidatorInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Configure ip_login settings.
 */
class IpLoginSettingsForm extends ConfigFormBase {

  /**
   * The cache factory.
   *
   * @var \Drupal\Core\Cache\CacheFactoryInterface
   */
  protected $cacheFactory;

  /**
   * The cache tags invalidator.
   *
   * @var \Drupal\Core\Cache\CacheTagsInvalidatorInterface
   */
  protected $cacheTagsInvalidator;

  /**
   * Constructs a PathautoSettingsForm.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   Defines the configuration object factory.
   * @param \Drupal\Core\Cache\CacheFactoryInterface $cache_factory
   *   The cache factory.
   * @param \Drupal\Core\Cache\CacheTagsInvalidatorInterface $cache_tags_invalidator
   *   The cache tags invalidator.
   */
  public function __construct(ConfigFactoryInterface $config_factory, CacheFactoryInterface $cache_factory, CacheTagsInvalidatorInterface $cache_tags_invalidator) {
    parent::__construct($config_factory);
    $this->cacheFactory = $cache_factory;
    $this->cacheTagsInvalidator = $cache_tags_invalidator;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('cache_factory'),
      $container->get('cache_tags.invalidator')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'ip_login_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['ip_login.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildForm($form, $form_state);
    $config = $this->config('ip_login.settings');

    $form['auto_login'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Login automatically'),
      '#description' => $this->t('When an anonymous user accesses any page of the site, the module will attempt to log them in automatically.'),
      '#default_value' => $config->get('auto_login'),
    ];
    $form['form_login'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Login form'),
      '#description' => $this->t('An additional action link is added to the user login form.'),
      '#default_value' => $config->get('form_login'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);

    $config = $this->config('ip_login.settings');
    $form_state->cleanValues();

    $auto_login = $form_state->getValue('auto_login');
    $form_login = $form_state->getValue('form_login');

    $old_auto_login = $config->get('auto_login');
    $config->set('auto_login', $auto_login);
    $old_form_login = $config->get('form_login');
    $config->set('form_login', $form_login);

    // If the login mode is changed, we need to clear the render cache so the
    // auto-login can be attempted on subsequent page loads, if configured.
    if ($auto_login != $old_auto_login || $form_login != $old_form_login) {
      $this->cacheFactory->get('render')->deleteAll();
      $this->cacheTagsInvalidator->invalidateTags(['ip_login']);

      // We need to invalidate the container so the middleware services are
      // registered based on the settings above.
      // @see \Drupal\ip_login\IpLoginServiceProvider::register()
      \Drupal::service('kernel')->invalidateContainer();
    }

    $config->save();
  }

}
