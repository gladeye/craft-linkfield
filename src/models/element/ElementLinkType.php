<?php

namespace lenz\linkfield\models\element;

use craft\base\ElementInterface;
use lenz\linkfield\fields\LinkField;
use lenz\linkfield\listeners\ElementListener;
use lenz\linkfield\models\Link;
use lenz\linkfield\models\LinkType;
use lenz\linkfield\records\LinkRecord;

/**
 * Class ElementLinkType
 */
class ElementLinkType extends LinkType
{
  /**
   * @var bool
   */
  public $allowCrossSiteLink = false;

  /**
   * @var bool
   */
  public $allowCustomQuery = false;

  /**
   * @var ElementInterface|string
   */
  public $elementType;

  /**
   * @var string
   */
  public $sources = '*';

  /**
   * @inheritDoc
   */
  const MODEL_CLASS = ElementLink::class;


  /**
   * @inheritDoc
   */
  public function createRecord(Link $link, LinkRecord $record) {
    parent::createRecord($link, $record);

    if (
      $link->getField()->enableElementCache &&
      $link instanceof ElementLink
    ) {
      $element = $link->getElement();
      if ($element && ElementListener::isElementPublished($element)) {
        $record->linkedTitle = (string)$element;
        $record->linkedUrl   = $element->getUrl();
      } else {
        $record->linkedTitle = null;
        $record->linkedUrl   = null;
      }
    }
  }

  /**
   * @return array
   */
  public function getAvailableSources() {
    $elementType = $this->elementType;
    $options = array();

    foreach ($elementType::sources() as $source) {
      if (array_key_exists('key', $source) && $source['key'] !== '*') {
        $options[$source['key']] = $source['label'];
      }
    }

    return $options;
  }

  /**
   * @inheritDoc
   */
  public function getDisplayName(): string {
    return $this->elementType::displayName();
  }

  /**
   * @inheritDoc
   */
  public function getInputHtml(Link $value, bool $disabled): string {
    return \Craft::$app->view->renderTemplate(
      'typedlinkfield/_input-element',
      [
        'disabled'     => $disabled,
        'elementField' => $this->getElementField($value),
        'linkType'     => $this,
        'queryField'   => $this->getQueryField($value),
        'selected'     => $this->isSelected($value),
        'siteField'    => $this->getSiteField($value),
      ]
    );
  }

  /**
   * @inheritDoc
   */
  public function getSettingsHtml(LinkField $field): string {
    return \Craft::$app->view->renderTemplate(
      'typedlinkfield/_settings-element',
      [
        'linkType' => $this,
      ]
    );
  }

  /**
   * @inheritDoc
   */
  public function rules() {
    return array_merge(parent::rules(), [
      ['allowCustomQuery', 'boolean'],
      ['allowCrossSiteLink', 'boolean'],
      ['elementType', 'validateElementType'],
      ['sources', 'validateSources'],
    ]);
  }

  /**
   * @inheritDoc
   */
  public function settingsAttributes(): array {
    return array_merge(parent::settingsAttributes(), [
      'allowCustomQuery',
      'allowCrossSiteLink',
      'sources',
    ]);
  }

  /**
   * @return void
   */
  public function validateElementType() {
    if (!is_subclass_of($this->elementType, ElementInterface::class)) {
      $this->addError('elementType', 'Element type must be a class implementing ElementInterface');
    }
  }

  /**
   * @inheritdoc
   */
  public function validateSources() {
    $availableSources = $this->getAvailableSources();
    $sources = $this->sources;
    if (!is_array($sources)) {
      $sources = [$sources];
    }

    $resolvedResources = array();
    foreach ($sources as $source) {
      if ($source == '*') {
        $this->sources = '*';
        return;
      }

      if (array_key_exists($source, $availableSources)) {
        $resolvedResources[] = $source;
      }
    }

    $this->sources = $resolvedResources;
  }


  // Protected methods
  // -----------------

  /**
   * @param Link $value
   * @return array
   */
  protected function getElementField(Link $value) {
    $owner = $value->getOwner();

    if ($this->isSelected($value) && $value instanceof ElementLink) {
      $linkedElements = array_filter([ $value->getElement() ]);
      $linkedSiteId   = $value->getSiteId();
    } else {
      $linkedElements = null;
      $linkedSiteId   = $value->getOwnerSite()->id;
    }

    $criteria = [
      'enabledForSite' => null,
      'status'         => null,
    ];

    if (!$this->allowCrossSiteLink) {
      $criteria['siteId'] = $linkedSiteId;
    }

    return $this->getFieldSettings(
      $value,
      'linkedId',
      [
        'criteria'        => $criteria,
        'elementType'     => $this->elementType,
        'elements'        => $linkedElements,
        'limit'           => 1,
        'showSiteMenu'    => $this->allowCrossSiteLink,
        'sourceElementId' => is_null($owner) ? null : $owner->getId(),
        'sources'         => $this->sources === '*' ? null : $this->sources,
        'storageKey'      => "linkfield.{$value->getField()->handle}.{$this->name}",
      ]
    );
  }

  /**
   * @param Link $value
   * @return array|null
   */
  protected function getQueryField(Link $value) {
    if (!$this->allowCustomQuery) {
      return null;
    }

    return $this->getFieldSettings(
      $value,
      'customQuery',
      [
        'placeholder' => \Craft::t('typedlinkfield', 'Query, starts with "#" or "?"'),
        'value'       => empty($value->customQuery) ? '' : $value->customQuery,
      ]
    );
  }

  /**
   * @param Link $value
   * @return array|null
   */
  protected function getSiteField(Link $value) {
    if (!$this->allowCrossSiteLink) {
      return null;
    }

    if ($value instanceof ElementLink) {
      $siteId = $value->getSiteId();
    } else {
      $siteId = $value->getOwnerSite()->id;
    }

    return $this->getFieldSettings(
      $value,
      'linkedSiteId',
      [
        'value' => $siteId,
      ]
    );
  }

  /**
   * @inheritDoc
   */
  protected function prepareFormData(array $data): array {
    if (isset($data['linkedId']) && is_array($data['linkedId'])) {
      $data['linkedId'] = reset($data['linkedId']);
    }

    return $data;
  }

  /**
   * @inheritDoc
   */
  protected function prepareLegacyData($data) {
    if (!is_numeric($data)) {
      return null;
    }

    return [
      'linkedId' => $data
    ];
  }
}
