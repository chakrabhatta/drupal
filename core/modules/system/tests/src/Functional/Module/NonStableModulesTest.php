<?php

namespace Drupal\Tests\system\Functional\Module;

use Drupal\Tests\BrowserTestBase;
use Drupal\user\UserInterface;

/**
 * Tests the installation of deprecated and experimental modules.
 *
 * @group Module
 */
class NonStableModulesTest extends BrowserTestBase {

  /**
   * The admin user.
   *
   * @var \Drupal\user\UserInterface
   */
  protected UserInterface $adminUser;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'deprecated_module_test',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->adminUser = $this->drupalCreateUser([
      'access administration pages',
      'administer modules',
    ]);
    $this->drupalLogin($this->adminUser);
  }

  /**
   * Tests installing experimental modules and dependencies in the UI.
   */
  public function testExperimentalConfirmForm(): void {
    // First, test installing a non-experimental module with no dependencies.
    // There should be no confirmation form and no experimental module warning.
    $edit = [];
    $edit["modules[test_page_test][enable]"] = TRUE;
    $this->drupalGet('admin/modules');
    $this->submitForm($edit, 'Install');
    $this->assertSession()->pageTextContains('Module Test page has been enabled.');
    $this->assertSession()->pageTextNotContains('Experimental modules are provided for testing purposes only.');

    // There should be no warning about enabling experimental or deprecated
    // modules, since there's no confirmation form.
    $this->assertSession()->pageTextNotContains('Are you sure you wish to enable ');

    // Uninstall the module.
    \Drupal::service('module_installer')->uninstall(['test_page_test']);

    // Next, test installing an experimental module with no dependencies.
    // There should be a confirmation form with an experimental warning, but no
    // list of dependencies.
    $edit = [];
    $edit["modules[experimental_module_test][enable]"] = TRUE;
    $this->drupalGet('admin/modules');
    $this->submitForm($edit, 'Install');

    // The module should not be enabled and there should be a warning and a
    // list of the experimental modules with only this one.
    $this->assertSession()->pageTextNotContains('Experimental Test has been enabled.');
    $this->assertSession()->pageTextContains('Experimental modules are provided for testing purposes only.');
    $this->assertSession()->pageTextContains('The following module is experimental: Experimental Test');

    // There should be a warning about enabling experimental modules, but no
    // warnings about deprecated modules.
    $this->assertSession()->pageTextContains('Are you sure you wish to enable an experimental module?');
    $this->assertSession()->pageTextNotContains('Are you sure you wish to enable a deprecated module?');
    $this->assertSession()->pageTextNotContains('Are you sure you wish to enable experimental and deprecated modules?');

    // There should be no message about enabling dependencies.
    $this->assertSession()->pageTextNotContains('You must enable');

    // Enable the module and confirm that it worked.
    $this->submitForm([], 'Continue');
    $this->assertSession()->pageTextContains('Experimental Test has been enabled.');

    // Uninstall the module.
    \Drupal::service('module_installer')->uninstall(['experimental_module_test']);

    // Test enabling a module that is not itself experimental, but that depends
    // on an experimental module.
    $edit = [];
    $edit["modules[experimental_module_dependency_test][enable]"] = TRUE;
    $this->drupalGet('admin/modules');
    $this->submitForm($edit, 'Install');

    // The module should not be enabled and there should be a warning and a
    // list of the experimental modules with only this one.
    $this->assertSession()->pageTextNotContains('2 modules have been enabled: Experimental Dependency Test, Experimental Test');
    $this->assertSession()->pageTextContains('Experimental modules are provided for testing purposes only.');
    $this->assertSession()->pageTextContains('The following module is experimental: Experimental Test');

    // There should be a warning about enabling experimental modules, but no
    // warnings about deprecated modules.
    $this->assertSession()->pageTextContains('Are you sure you wish to enable an experimental module?');
    $this->assertSession()->pageTextNotContains('Are you sure you wish to enable a deprecated module?');
    $this->assertSession()->pageTextNotContains('Are you sure you wish to enable experimental and deprecated modules?');

    // Ensure the non-experimental module is not listed as experimental.
    $this->assertSession()->pageTextNotContains('The following modules are experimental: Experimental Test, Experimental Dependency Test');
    $this->assertSession()->pageTextNotContains('The following module is experimental: Experimental Dependency Test');

    // There should be a message about enabling dependencies.
    $this->assertSession()->pageTextContains('You must enable the Experimental Test module to install Experimental Dependency Test');

    // Enable the module and confirm that it worked.
    $this->submitForm([], 'Continue');
    $this->assertSession()->pageTextContains('2 modules have been enabled: Experimental Dependency Test, Experimental Test');

    // Uninstall the modules.
    \Drupal::service('module_installer')->uninstall([
      'experimental_module_test',
      'experimental_module_dependency_test',
    ]);

    // Finally, check both the module and its experimental dependency. There is
    // still a warning about experimental modules, but no message about
    // dependencies, since the user specifically enabled the dependency.
    $edit = [];
    $edit["modules[experimental_module_test][enable]"] = TRUE;
    $edit["modules[experimental_module_dependency_test][enable]"] = TRUE;
    $this->drupalGet('admin/modules');
    $this->submitForm($edit, 'Install');

    // The module should not be enabled and there should be a warning and a
    // list of the experimental modules with only this one.
    $this->assertSession()->pageTextNotContains('2 modules have been enabled: Experimental Dependency Test, Experimental Test');
    $this->assertSession()->pageTextContains('Experimental modules are provided for testing purposes only.');
    $this->assertSession()->pageTextContains('The following module is experimental: Experimental Test');

    // There should be a warning about enabling experimental modules, but no
    // warnings about deprecated modules.
    $this->assertSession()->pageTextContains('Are you sure you wish to enable an experimental module?');
    $this->assertSession()->pageTextNotContains('Are you sure you wish to enable a deprecated module?');
    $this->assertSession()->pageTextNotContains('Are you sure you wish to enable experimental and deprecated modules?');

    // Ensure the non-experimental module is not listed as experimental.
    $this->assertSession()->pageTextNotContains('The following modules are experimental: Experimental Dependency Test, Experimental Test');
    $this->assertSession()->pageTextNotContains('The following module is experimental: Experimental Dependency Test');

    // There should be no message about enabling dependencies.
    $this->assertSession()->pageTextNotContains('You must enable');

    // Enable the module and confirm that it worked.
    $this->submitForm([], 'Continue');
    $this->assertSession()->pageTextContains('2 modules have been enabled: Experimental Dependency Test, Experimental Test');

    // Try to enable an experimental module that can not be due to
    // hook_requirements().
    \Drupal::state()->set('experimental_module_requirements_test_requirements', TRUE);
    $edit = [];
    $edit["modules[experimental_module_requirements_test][enable]"] = TRUE;
    $this->drupalGet('admin/modules');
    $this->submitForm($edit, 'Install');

    // Verify that if the module can not be installed, we are not taken to the
    // confirm form.
    $this->assertSession()->addressEquals('admin/modules');
    $this->assertSession()->pageTextContains('The Experimental Test Requirements module can not be installed.');
  }

  /**
   * Tests installing deprecated modules and dependencies in the UI.
   */
  public function testDeprecatedConfirmForm(): void {
    // Test installing a deprecated module with no dependencies. There should be
    // a confirmation form with a deprecated warning, but no list of
    // dependencies.
    $edit = [];
    $edit["modules[deprecated_module][enable]"] = TRUE;
    $this->drupalGet('admin/modules');
    $this->submitForm($edit, 'Install');

    // The module should not be enabled and there should be a warning and a
    // list of the deprecated modules with only this one.
    $assert = $this->assertSession();
    $assert->pageTextNotContains('Deprecated module has been enabled.');
    $assert->pageTextContains('Deprecated modules are modules that may be removed from the next major release of Drupal core. Use at your own risk.');
    $assert->pageTextContains('The Deprecated module module is deprecated');
    $more_information_link = $assert->elementExists('named', [
      'link',
      'The Deprecated module module is deprecated. (more information)',
    ]);
    $this->assertEquals('http://example.com/deprecated', $more_information_link->getAttribute('href'));

    // There should be a warning about enabling deprecated modules, but no
    // warnings about experimental modules.
    $this->assertSession()->pageTextContains('Are you sure you wish to enable a deprecated module?');
    $this->assertSession()->pageTextNotContains('Are you sure you wish to enable an experimental module?');
    $this->assertSession()->pageTextNotContains('Are you sure you wish to enable experimental and deprecated modules?');

    // There should be no message about enabling dependencies.
    $assert->pageTextNotContains('You must enable');

    // Enable the module and confirm that it worked.
    $this->submitForm([], 'Continue');
    $assert->pageTextContains('Deprecated module has been enabled.');

    // Uninstall the module.
    \Drupal::service('module_installer')->uninstall(['deprecated_module']);

    // Test enabling a module that is not itself deprecated, but that depends on
    // a deprecated module.
    $edit = [];
    $edit["modules[deprecated_module_dependency][enable]"] = TRUE;
    $this->drupalGet('admin/modules');
    $this->submitForm($edit, 'Install');

    // The module should not be enabled and there should be a warning and a
    // list of the deprecated modules with only this one.
    $assert->pageTextNotContains('2 modules have been enabled: Deprecated module dependency, Deprecated module');
    $assert->pageTextContains('Deprecated modules are modules that may be removed from the next major release of Drupal core. Use at your own risk.');
    $assert->pageTextContains('The Deprecated module module is deprecated');

    // There should be a warning about enabling deprecated modules, but no
    // warnings about experimental modules.
    $this->assertSession()->pageTextContains('Are you sure you wish to enable a deprecated module?');
    $this->assertSession()->pageTextNotContains('Are you sure you wish to enable an experimental module?');
    $this->assertSession()->pageTextNotContains('Are you sure you wish to enable experimental and deprecated modules?');

    // Ensure the non-deprecated module is not listed as deprecated.
    $assert->pageTextNotContains('The Deprecated module dependency module is deprecated');

    // There should be a message about enabling dependencies.
    $assert->pageTextContains('You must enable the Deprecated module module to install Deprecated module dependency');

    // Enable the module and confirm that it worked.
    $this->submitForm([], 'Continue');
    $assert->pageTextContains('2 modules have been enabled: Deprecated module dependency, Deprecated module');

    // Uninstall the modules.
    \Drupal::service('module_installer')->uninstall([
      'deprecated_module',
      'deprecated_module_dependency',
    ]);

    // Finally, check both the module and its deprecated dependency. There is
    // still a warning about deprecated modules, but no message about
    // dependencies, since the user specifically enabled the dependency.
    $edit = [];
    $edit["modules[deprecated_module_dependency][enable]"] = TRUE;
    $edit["modules[deprecated_module][enable]"] = TRUE;
    $this->drupalGet('admin/modules');
    $this->submitForm($edit, 'Install');

    // The module should not be enabled and there should be a warning and a
    // list of the deprecated modules with only this one.
    $assert->pageTextNotContains('2 modules have been enabled: Deprecated module dependency, Deprecated module');
    $assert->pageTextContains('Deprecated modules are modules that may be removed from the next major release of Drupal core. Use at your own risk.');
    $assert->pageTextContains('The Deprecated module module is deprecated');

    // There should be a warning about enabling deprecated modules, but no
    // warnings about experimental modules.
    $this->assertSession()->pageTextContains('Are you sure you wish to enable a deprecated module?');
    $this->assertSession()->pageTextNotContains('Are you sure you wish to enable an experimental module?');
    $this->assertSession()->pageTextNotContains('Are you sure you wish to enable experimental and deprecated modules?');

    // Ensure the non-deprecated module is not listed as deprecated.
    $assert->pageTextNotContains('The Deprecated module dependency module is deprecated');

    // There should be no message about enabling dependencies.
    $assert->pageTextNotContains('You must enable');

    // Enable the module and confirm that it worked.
    $this->submitForm([], 'Continue');
    $assert->pageTextContains('2 modules have been enabled: Deprecated module, Deprecated module dependency');

    $this->drupalGet('admin/modules');
    $this->submitForm(["modules[deprecated_module_contrib][enable]" => TRUE], 'Install');
    $assert->pageTextContains('Deprecated modules are modules that may be removed from the next major release of this project. Use at your own risk.');

    \Drupal::service('module_installer')->uninstall([
      'deprecated_module',
      'deprecated_module_dependency',
    ]);
    $this->drupalGet('admin/modules');
    $this->submitForm([
      "modules[deprecated_module_contrib][enable]" => TRUE,
      "modules[deprecated_module][enable]" => TRUE,
    ], 'Install');
    $assert->pageTextContains('Deprecated modules are modules that may be removed from the next major release of Drupal core and the relevant contributed module. Use at your own risk.');
  }

  /**
   * Tests installing deprecated and experimental modules at the same time.
   */
  public function testDeprecatedAndExperimentalConfirmForm(): void {
    $edit = [];
    $edit["modules[deprecated_module][enable]"] = TRUE;
    $edit["modules[experimental_module_test][enable]"] = TRUE;
    $this->drupalGet('admin/modules');
    $this->submitForm($edit, 'Install');

    // The module should not be enabled and there should be a warning and a
    // list of the deprecated modules with only this one.
    $assert = $this->assertSession();
    $assert->pageTextNotContains('Deprecated module has been enabled.');
    $assert->pageTextContains('Deprecated modules are modules that may be removed from the next major release of Drupal core. Use at your own risk.');
    $assert->pageTextContains('The Deprecated module module is deprecated');
    $more_information_link = $assert->elementExists('named', [
      'link',
      'The Deprecated module module is deprecated. (more information)',
    ]);
    $this->assertEquals('http://example.com/deprecated', $more_information_link->getAttribute('href'));

    // The module should not be enabled and there should be a warning and a
    // list of the experimental modules with only this one.
    $assert->pageTextNotContains('Experimental Test has been enabled.');
    $assert->pageTextContains('Experimental modules are provided for testing purposes only.');
    $assert->pageTextContains('The following module is experimental: Experimental Test');

    // There should be a warning about enabling experimental and deprecated
    // modules, but no warnings about solitary experimental or deprecated
    // modules.
    $this->assertSession()->pageTextContains('Are you sure you wish to enable experimental and deprecated modules?');
    $this->assertSession()->pageTextNotContains('Are you sure you wish to enable experimental modules?');
    $this->assertSession()->pageTextNotContains('Are you sure you wish to enable deprecated modules?');

    // There should be no message about enabling dependencies.
    $assert->pageTextNotContains('You must enable');

    // Enable the module and confirm that it worked.
    $this->submitForm([], 'Continue');
    $assert->pageTextContains('2 modules have been enabled: Deprecated module, Experimental Test.');
  }

}
