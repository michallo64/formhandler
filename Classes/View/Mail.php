<?php

declare(strict_types=1);

namespace Typoheads\Formhandler\View;

/**
 * This script is part of the TYPO3 project - inspiring people to share!
 *
 * TYPO3 is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License version 2 as published by
 * the Free Software Foundation.
 *
 * This script is distributed in the hope that it will be useful, but
 * WITHOUT ANY WARRANTY; without even the implied warranty of MERCHAN-
 * TABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General
 * Public License for more details.
 */

/**
 * A default view for Formhandler E-Mails.
 */
class Mail extends Form {
  /** @var array<string, mixed> */
  protected array $currentMailSettings;

  /**
   * Wraps the input string in a <div> tag with the class attribute set to the prefixId.
   * All content returned from your plugins should be returned through this function so all content from your plugin is encapsulated in a <div>-tag nicely identifying the content of your plugin.
   *
   * @param string $content HTML content to wrap in the div-tags with the "main class" of the plugin
   *
   * @return string HTML content wrapped, ready to return to the parent object
   */
  public function pi_wrapInBaseClass($content) {
    return $content;
  }

  /**
   * Main method called by the controller.
   *
   * @param array<string, mixed> $gp     The current GET/POST parameters
   * @param array<string, mixed> $errors In this class the second param is used to pass information about the email mode (HTML|PLAIN)
   *
   * @return string content
   */
  public function render(array $gp, array $errors): string {
    $this->currentMailSettings = $errors;
    $content = '';
    if (strlen(strval($this->subparts['template'] ?? '')) > 0) {
      $this->settings = $this->globals->getSettings();
      $content = parent::render($gp, []);
    }

    return $content;
  }

  protected function fillEmbedMarkers(): void {
    $mailSettings = $this->getComponentSettings();
    if (isset($mailSettings['embedFiles']) && is_array($mailSettings['embedFiles'])) {
      $markers = [];
      foreach ($mailSettings['embedFiles'] as $key => $cid) {
        $markers['###embed_'.$key.'###'] = $cid;
      }
      $this->template = $this->markerBasedTemplateService->substituteMarkerArray($this->template, $markers);
    }
  }

  protected function fillValueMarkers(): void {
    $mailSettings = $this->getComponentSettings();
    if (
      isset($mailSettings[$this->currentMailSettings['suffix'].'.']) && is_array($mailSettings[$this->currentMailSettings['suffix'].'.']) && isset($mailSettings[$this->currentMailSettings['suffix'].'.']['arrayValueSeparator'])
      && $mailSettings[$this->currentMailSettings['suffix'].'.']['arrayValueSeparator']
    ) {
      $this->settings['arrayValueSeparator'] = $mailSettings[$this->currentMailSettings['suffix'].'.']['arrayValueSeparator'];
      $this->settings['arrayValueSeparator.'] = $mailSettings[$this->currentMailSettings['suffix'].'.']['arrayValueSeparator.'];
    }
    $this->disableEncodingFields = [];
    if (isset($this->settings['disableEncodingFields'])) {
      $this->disableEncodingFields = explode(',', $this->utilityFuncs->getSingle($this->settings, 'disableEncodingFields'));
    }

    /*
     * getValueMarkers() will call htmlSpecialChars on all values before adding them to the marker array.
     * In case of a plain text email, this is unwanted behavior.
     */
    $doEncode = true;
    if ('plain' === $this->currentMailSettings['suffix']) {
      $doEncode = false;
    }
    $markers = $this->getValueMarkers($this->gp, 0, 'value_', $doEncode);

    if ('plain' !== $this->currentMailSettings['suffix']) {
      $markers = $this->sanitizeMarkers($markers);
    }

    $this->template = $this->markerBasedTemplateService->substituteMarkerArray($this->template, $markers);

    // remove remaining VALUE_-markers
    // needed for nested markers like ###LLL:tx_myextension_table.field1.i.###value_field1###### to avoid wrong marker removal if field1 isn't set
    $this->template = preg_replace('/###value_.*?###/i', '', $this->template) ?? '';
    $this->fillEmbedMarkers();
  }
}
