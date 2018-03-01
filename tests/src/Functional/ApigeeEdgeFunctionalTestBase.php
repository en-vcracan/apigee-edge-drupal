<?php

/**
 * Copyright 2018 Google Inc.
 *
 * This program is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License version 2 as published by the
 * Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful, but WITHOUT
 * ANY WARRANTY; without even the implied warranty of MERCHANTABILITY
 * or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public
 * License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc., 51
 * Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 */

namespace Drupal\Tests\apigee_edge\Functional;

use Drupal\apigee_edge\Entity\ApiProduct;
use Drupal\apigee_edge\Entity\Developer;
use Drupal\Tests\BrowserTestBase;
use Drupal\user\Entity\User;
use Drupal\user\UserInterface;

abstract class ApigeeEdgeFunctionalTestBase extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  public static $modules = [
    'apigee_edge',
    'apigee_edge_test',
  ];

  /**
   * Creates a Drupal account.
   *
   * @param array $permissions
   * @param bool $status
   * @param string $prefix
   *
   * @return \Drupal\user\UserInterface
   *   Drupal user.
   */
  protected function createAccount(array $permissions = [], bool $status = TRUE, string $prefix = '') : ?UserInterface {
    $rid = NULL;
    if ($permissions) {
      $rid = $this->createRole($permissions);
      $this->assertTrue($rid, 'Role created');
    }

    $edit = [
      'first_name' => $this->randomMachineName(),
      'last_name' => $this->randomMachineName(),
      'name' => $this->randomMachineName(),
      'pass' => user_password(),
      'status' => $status,
    ];
    if ($rid) {
      $edit['roles'][] = $rid;
    }
    if ($prefix) {
      $edit['mail'] = "{$prefix}.{$edit['name']}@example.com";
    }
    else {
      $edit['mail'] = "{$edit['name']}@example.com";
    }

    $account = User::create($edit);
    $account->save();

    $this->assertTrue($account->id(), 'User created.');
    if (!$account->id()) {
      return NULL;
    }

    // This is here to make drupalLogin() work.
    $account->passRaw = $edit['pass'];

    return $account;
  }

  /**
   * Creates a product.
   *
   * @return \Drupal\apigee_edge\Entity\ApiProduct
   *   (SDK) API product object.
   */
  protected function createProduct() : ApiProduct {
    /** @var \Drupal\apigee_edge\Entity\ApiProduct $product */
    $product = ApiProduct::create([
      'name' => $this->randomMachineName(),
      'displayName' => $this->getRandomGenerator()->word(16),
      'approvalType' => ApiProduct::APPROVAL_TYPE_AUTO,
    ]);
    $product->save();

    return $product;
  }

  /**
   * Loads all apps for a given user.
   *
   * @param string $email
   *   Email address of a user.
   *
   * @return \Drupal\apigee_edge\Entity\DeveloperApp[]|null
   *   Array of developer apps of the user or if user does not exist as
   *   developer on Edge.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   */
  protected function getApps(string $email): ?array {
    $developer = Developer::load($email);
    if ($developer) {
      /** @var \Drupal\apigee_edge\Entity\Storage\DeveloperAppStorage $storage */
      $storage = \Drupal::entityTypeManager()->getStorage('developer_app');
      return $storage->loadByDeveloper($developer->uuid());
    }

    return NULL;
  }

  /**
   * The same as drupalGet(), but ignores the meta refresh.
   *
   * @param string $path
   *   Url path.
   * @param array $options
   *   Url options.
   * @param array $headers
   *   Additional http headers.
   *
   * @return string
   *   The retrieved HTML string, also available as $this->getRawContent()
   */
  protected function drupalGetNoMetaRefresh(string $path, array $options = [], array $headers = []) {
    $options['absolute'] = TRUE;
    $url = $this->buildUrl($path, $options);

    $session = $this->getSession();

    $this->prepareRequest();
    foreach ($headers as $header_name => $header_value) {
      $session->setRequestHeader($header_name, $header_value);
    }

    $session->visit($url);
    $out = $session->getPage()->getContent();

    $this->refreshVariables();

    return $out;
  }

  /**
   * Implements link clicking properly.
   *
   * The clickLink() function uses Mink, not drupalGet(). This means that
   * certain features (like chekcing for meta refresh) are not working at all.
   * This is a problem, because batch api works with meta refresh when JS is not
   * available.
   *
   * @param string $name
   */
  protected function clickLinkProperly(string $name) {
    list($path, $query) = $this->findLink($name);
    $this->drupalGet($path, [
      'query' => $query,
    ]);
  }

  /**
   * Finds a link on the current page.
   *
   * @param string $name
   *   Name of the link.
   *
   * @return array
   *   An array with two items. The first one is the path, the second one is
   *   an associative array of the query parameters.
   */
  protected function findLink(string $name): array {
    /** @var \Behat\Mink\Element\NodeElement[] $links */
    $links = $this->getSession()->getPage()->findAll('named', ['link', $name]);
    $this->assertnotEmpty($links, "Link \"{$name}\" found.");

    $href = $links[0]->getAttribute('href');
    $parts = parse_url($href);
    $query = [];
    parse_str($parts['query'], $query);

    return [$parts['path'], $query];
  }

}
