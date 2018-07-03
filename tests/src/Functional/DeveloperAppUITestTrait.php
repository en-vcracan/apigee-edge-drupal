<?php

/**
 * Copyright 2018 Google Inc.
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * version 2 as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston,
 * MA 02110-1301, USA.
 */

namespace Drupal\Tests\apigee_edge\Functional;

use Apigee\Edge\Structure\CredentialProduct;
use Drupal\apigee_edge\Entity\ApiProduct;
use Drupal\apigee_edge\Entity\Developer;
use Drupal\apigee_edge\Entity\DeveloperApp;
use Drupal\user\UserInterface;

/**
 * Contains re-usable components for developer app UI tests.
 */
trait DeveloperAppUITestTrait {

  use ApigeeEdgeTestTrait;

  /**
   * Default user.
   *
   * @var \Drupal\user\Entity\User
   */
  protected $account;

  /**
   * Default product.
   *
   * @var \Drupal\apigee_edge\Entity\ApiProduct[]
   */
  protected $products = [];

  protected static $permissions = [
    'administer apigee edge',
    'create developer_app',
    'view own developer_app',
    'update own developer_app',
    'delete own developer_app',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    // We can not override self::$modules in this trait because that would
    // conflict with \Drupal\Tests\BrowserTestBase::$modules where both
    // traits are being used.
    $this->installExtraModules(['block']);
    $this->drupalPlaceBlock('local_tasks_block');

    $config = \Drupal::configFactory()->getEditable('apigee_edge.dangerzone');
    $config->set('skip_developer_app_settings_validation', TRUE);
    $config->save();

    $this->products[] = $this->createProduct();
    $this->account = $this->createAccount(static::$permissions);
    $this->drupalLogin($this->account);
  }

  /**
   * {@inheritdoc}
   */
  protected function tearDown() {
    $this->account->delete();
    foreach ($this->products as $product) {
      $product->delete();
    }
    parent::tearDown();
  }

  /**
   * Goes to the users' create app form.
   *
   * @param \Drupal\user\UserInterface|null $account
   */
  protected function gotoCreateAppForm(?UserInterface $account = NULL) {
    if ($account === NULL) {
      $account = $this->account;
    }
    $this->drupalGet("/user/{$account->id()}/apps/create");
  }

  /**
   * Submits the create app admin form.
   *
   * @param array $changes
   *   Settings to save.
   */
  protected function submitAdminForm(array $changes = []) {
    $this->drupalGet('/admin/config/apigee-edge/app-settings');
    $data = $changes + [
      'display_as_select' => FALSE,
      'user_select' => TRUE,
      'multiple_products' => TRUE,
    ];
    $multiple_products = $data['multiple_products'];
    unset($data['multiple_products']);
    $this->config('apigee_edge.common_app_settings')
      ->set('multiple_products', $multiple_products)
      ->save();
    $this->drupalPostForm('/admin/config/apigee-edge/app-settings', $data, 'Save configuration');
  }

  /**
   * Posts the create app form.
   *
   * @param array $data
   *   Data to post.
   * @param \Drupal\user\UserInterface|null $account
   *   Owner of the form.
   */
  protected function postCreateAppForm(array $data, ?UserInterface $account = NULL) {
    if ($account === NULL) {
      $account = $this->account;
    }

    $this->drupalPostForm("/user/{$account->id()}/apps/create", $data, 'Add developer app');
  }

  protected function postEditAppForm(array $data, string $app_name, ?UserInterface $account = NULL) {
    if ($account === NULL) {
      $account = $this->account;
    }

    $this->drupalPostForm("/user/{$account->id()}/apps/{$app_name}/edit", $data, 'Save');
  }

  /**
   * {@inheritdoc}
   */
  protected function getApps(?string $email = NULL): ?array {
    if ($email === NULL) {
      $email = $this->account->getEmail();
    }

    return parent::getApps($email);
  }

  /**
   * Asserts that a certain app exists.
   *
   * @param string $name
   *   Name of the app.
   *
   * @return \Drupal\apigee_edge\Entity\DeveloperApp|null
   *   Developer app or null.
   */
  protected function assertDeveloperAppExists(string $name) : ?DeveloperApp {
    /** @var \Drupal\apigee_edge\Entity\DeveloperApp[] $apps */
    $apps = $this->getApps();
    $found = NULL;
    foreach ($apps as $app) {
      if ($app->getName() === $name) {
        $found = $app;
        break;
      }
    }

    $this->assertNotNull($found, 'Developer app name found.');
    return $found;
  }

  /**
   * Goes through a typical CRUD cycle for an app.
   *
   * @param callable|null $beforeCreate
   *   Alters the data that will be posted on the create form.
   * @param callable|null $afterCreate
   *   Additional asserts after the app is created.
   * @param callable|null $beforeUpdate
   *   Alters the data that will be posted on the update form.
   * @param callable|null $afterUpdate
   *   Additional asserts after the app is created.
   * @param \Drupal\user\UserInterface|null $account
   */
  protected function assertAppCrud(?callable $beforeCreate = NULL, ?callable $afterCreate = NULL, ?callable $beforeUpdate = NULL, ?callable $afterUpdate = NULL, ?UserInterface $account = NULL) {
    if ($account === NULL) {
      $account = $this->account;
    }

    $name = strtolower($this->randomMachineName());
    $displayName = $this->getRandomGenerator()->word(16);
    $callbackUrl = "http://example.com/{$this->randomMachineName()}";
    $description = trim($this->getRandomGenerator()->paragraphs(1));

    $data = [
      'name' => $name,
      'displayName[0][value]' => $displayName,
      'callbackUrl[0][value]' => $callbackUrl,
      'description[0][value]' => $description,
    ];
    if ($beforeCreate) {
      $data = $beforeCreate($data);
    }

    $this->postCreateAppForm($data, $account);

    $app = $this->loadDeveloperApp($name);

    $this->assertSession()->linkByHrefExists("/user/{$account->id()}/apps/{$app->getName()}/edit?destination=/user/{$account->id()}/apps");
    $this->assertSession()->linkByHrefExists("/user/{$account->id()}/apps/{$app->getName()}/delete?destination=/user/{$account->id()}/apps");
    $this->clickLink($displayName);
    $this->assertSession()->pageTextContains($displayName);
    $this->assertSession()->pageTextContains($callbackUrl);
    $this->assertSession()->pageTextContains($description);

    if ($afterCreate) {
      $afterCreate($name);
    }

    /** @var \Drupal\apigee_edge\Entity\Developer $developer */
    $developer = Developer::load($account->getEmail());
    /** @var \Drupal\apigee_edge\Entity\Storage\DeveloperAppStorageInterface $storage */
    $storage = \Drupal::entityTypeManager()->getStorage('developer_app');
    /** @var \Drupal\apigee_edge\Entity\DeveloperApp $app */
    $app = $storage->load(array_values($storage->getQuery()
      ->condition('developerId', $developer->uuid())
      ->condition('name', $name)
      ->execute())[0]);
    $this->assertNotNull($app);
    /** @var \Apigee\Edge\Api\Management\Entity\AppCredential[] $credentials */
    $credentials = $app->getCredentials();
    /** @var \Apigee\Edge\Api\Management\Entity\AppCredential $credential */
    $credential = reset($credentials);
    $credential_id = $credential->id();

    $displayName = $this->getRandomGenerator()->word(16);
    $callbackUrl = "{$callbackUrl}/{$this->randomMachineName()}";
    $description = trim($this->getRandomGenerator()->paragraphs(1));
    $data = [
      'displayName[0][value]' => $displayName,
      'callbackUrl[0][value]' => $callbackUrl,
      'description[0][value]' => $description,
    ];
    if ($beforeUpdate) {
      $data = $beforeUpdate($data, $credential_id);
    }

    $this->postEditAppForm($data, $name, $account);
    $this->assertSession()->pageTextContains($displayName);
    $this->assertSession()->pageTextContains($callbackUrl);
    $this->assertSession()->pageTextContains($description);

    if ($afterUpdate) {
      $afterUpdate($name);
    }

    $this->clickLink('Delete');
    $this->submitForm([
      'id_verification' => $name,
    ], 'Delete');

    $this->drupalGet("/user/{$account->id()}/apps");
    $this->assertSession()->pageTextNotContains($displayName);
  }

  /**
   * Creates an app and assigns products to it.
   *
   * @param \Drupal\apigee_edge\Entity\ApiProduct[] $products
   * @param bool $multiple
   *   Allow submitting multiple products.
   * @param bool $display_as_select
   *   Display the products as a select box.
   */
  protected function assertAppCreationWithProduct(array $products = [], bool $multiple = TRUE, bool $display_as_select = FALSE) {
    $this->submitAdminForm([
      'multiple_products' => $multiple,
      'display_as_select' => $display_as_select,
    ]);
    $name = strtolower($this->randomMachineName());

    $productnum = count($products);

    $formdata = [
      'name' => $name,
      'displayName[0][value]' => $name,
    ];
    if (count($products) === 1) {
      $formdata['api_products'] = reset($products)->getName();
    }
    elseif (count($products) > 1) {
      foreach ($products as $product) {
        $formdata["api_products[{$product->getName()}]"] = $product->getName();
      }
    }

    $this->postCreateAppForm($formdata);
    $app = $this->assertDeveloperAppExists($name);
    if ($app) {
      /** @var \Apigee\Edge\Api\Management\Entity\AppCredential[] $credentials */
      $credentials = $app->getCredentials();
      $this->assertEquals(1, count($credentials), 'Exactly one credential exists.');
      $credential = reset($credentials);

      $apiproducts = $credential->getApiProducts();
      $this->assertEquals($productnum, count($apiproducts), "Exactly {$productnum} product is added.");
      $expected_products = array_map(function (ApiProduct $apiProduct): string {
        return $apiProduct->getName();
      }, $products);
      $retrieved_products = array_map(function (CredentialProduct $credentialProduct): string {
        return $credentialProduct->getApiproduct();
      }, $apiproducts);
      sort($expected_products);
      sort($retrieved_products);
      $this->assertEquals($expected_products, $retrieved_products);

      $app->delete();
    }
  }

  /**
   * Loads a developer app by name.
   *
   * @param string $name
   *   Name of the developer app.
   *
   * @return \Drupal\apigee_edge\Entity\DeveloperApp|null
   *   Loaded developer app or null if not found.
   */
  protected function loadDeveloperApp(string $name): ?DeveloperApp {
    /** @var \Drupal\apigee_edge\Entity\DeveloperApp[] $apps */
    $apps = DeveloperApp::loadMultiple();

    foreach ($apps as $app) {
      if ($app->getName() === $name) {
        return $app;
      }
    }

    return NULL;
  }

}
