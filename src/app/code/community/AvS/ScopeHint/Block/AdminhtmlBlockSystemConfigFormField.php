<?php

/**
 * Render config field; hint added when config value is overwritten in a scope below
 *
 * @category   AvS
 * @package    AvS_ScopeHint
 * @author     Andreas von Studnitz <avs@avs-webentwicklung.de>
 */
class AvS_ScopeHint_Block_AdminhtmlBlockSystemConfigFormField
    extends Mage_Adminhtml_Block_System_Config_Form_Field
    implements Varien_Data_Form_Element_Renderer_Interface
{

    /** @var array */
    protected $_fullStoreNames = array();

    /**
     * Renders a config field; scope hint added
     *
     * @param Varien_Data_Form_Element_Abstract $element
     * @return string
     */
    public function render(Varien_Data_Form_Element_Abstract $element)
    {
        $id = $element->getHtmlId();

        $useContainerId = $element->getData('use_container_id');
        $html = '<tr id="row_' . $id . '">'
                . '<td class="label"><label for="' . $id . '">' . $element->getLabel() . '</label></td>';

        //$isDefault = !$this->getRequest()->getParam('website') && !$this->getRequest()->getParam('store');
        $isMultiple = $element->getExtType() === 'multiple';

        // replace [value] with [inherit]
        $namePrefix = preg_replace('#\[value\](\[\])?$#', '', $element->getName());

        $options = $element->getValues();

        $addInheritCheckbox = false;
        if ($element->getCanUseWebsiteValue()) {
            $addInheritCheckbox = true;
            $checkboxLabel = Mage::helper('adminhtml')->__('Use Website');
        }
        elseif ($element->getCanUseDefaultValue()) {
            $addInheritCheckbox = true;
            $checkboxLabel = Mage::helper('adminhtml')->__('Use Default');
        }

        if ($addInheritCheckbox) {
            $inherit = $element->getInherit() == 1 ? 'checked="checked"' : '';
            if ($inherit) {
                $element->setDisabled(true);
            }
        }

        if ($element->getTooltip()) {
            $html .= '<td class="value with-tooltip">';
            $html .= $this->_getElementHtml($element);
            $html .= '<div class="field-tooltip"><div>' . $element->getTooltip() . '</div></div>';
        } else {
            $html .= '<td class="value">';
            $html .= $this->_getElementHtml($element);
        };

        if ($element->getComment()) {
            $html .= '<p class="note"><span>' . $element->getComment() . '</span></p>';
        }
        $html .= '</td>';

        if ($addInheritCheckbox) {

            $defText = $element->getDefaultValue();
            if ($options) {
                $defTextArr = array();
                foreach ($options as $k => $v) {
                    if ($isMultiple) {
                        if (is_array($v['value']) && in_array($k, $v['value'])) {
                            $defTextArr[] = $v['label'];
                        }
                    } elseif ($v['value'] == $defText) {
                        $defTextArr[] = $v['label'];
                        break;
                    }
                }
                $defText = join(', ', $defTextArr);
            }

            // default value
            $html .= '<td class="use-default">';
            //$html.= '<input id="'.$id.'_inherit" name="'.$namePrefix.'[inherit]" type="checkbox" value="1" class="input-checkbox config-inherit" '.$inherit.' onclick="$(\''.$id.'\').disabled = this.checked">';
            $html .= '<input id="' . $id . '_inherit" name="' . $namePrefix . '[inherit]" type="checkbox" value="1" class="checkbox config-inherit" ' . $inherit . ' onclick="toggleValueElements(this, Element.previous(this.parentNode))" /> ';
            $html .= '<label for="' . $id . '_inherit" class="inherit" title="' . htmlspecialchars($defText) . '">' . $checkboxLabel . '</label>';
            $html .= '</td>';
        }

        $html .= '<td class="scope-label">';
        if ($element->getScope()) {
            $html .= $element->getScopeLabel();
        }
        $html .= '</td>';

        $html .= '<td class="scopehint" style="padding: 6px 6px 0 6px;">';
        $html .= $this->_getScopeHint($element);
        $html .= '</td>';

        $html .= '<td class="">';
        if ($element->getHint()) {
            $html .= '<div class="hint" >';
            $html .= '<div style="display: none;">' . $element->getHint() . '</div>';
            $html .= '</div>';
        }
        $html .= '</td>';

        $html .= '</tr>';
        return $html;
    }

    /**
     * @param Varien_Data_Form_Element_Abstract $element
     * @return string
     */
    protected function _getScopeHint($element)
    {
        $changedScopes = array();

        if ($this->_isStoreScope()) return '';

        if ($this->_isWebsiteScope()) {

            $website = $this->getWebsite();
            $changedScopes = $this->_getChangedStoresForWebsite($element, $website);
        }

        if ($this->_isGlobalScope()) {

            $changedScopes = $this->_getChangedScopesForGlobal($element);
        }

        if (empty($changedScopes)) return '';

        return $this->_getHintHtml($changedScopes);
    }

    /**
     * @param Varien_Data_Form_Element_Abstract $element
     * @return string
     */
    protected function _getConfigCode($element)
    {
        $configCode = preg_replace('#\[value\](\[\])?$#', '', $element->getName());
        $configCode = str_replace('[fields]', '', $configCode);
        $configCode = str_replace('groups[', '[', $configCode);
        $configCode = str_replace('][', '/', $configCode);
        $configCode = str_replace(']', '', $configCode);
        $configCode = str_replace('[', '', $configCode);
        $configCode = Mage::app()->getRequest()->getParam('section') . '/' . $configCode;
        return $configCode;
    }

    /**
     * @param Varien_Data_Form_Element_Abstract $element
     * @param Mage_Core_Model_Website $website
     * @return array
     */
    protected function _getChangedStoresForWebsite($element, $website)
    {
        $changedStores = array();

        foreach ($website->getStores() as $store) {

            /** @var Mage_Core_Model_Store $store */
            if ($this->_isConfigurationValueChanged($element, $store, $website)) {

                $changedStores[Mage::helper('scopehint')->__('Store View: %s', $this->_getFullStoreName($store))] = $this->_getReadableConfigValue($store, $element);
            }
        }
        return $changedStores;
    }

    /**
     * @param Varien_Data_Form_Element_Abstract $element
     * @return array
     */
    protected function _getChangedScopesForGlobal($element)
    {
        $changedScopes = array();

        foreach (Mage::app()->getWebsites() as $website) {

            /** @var Mage_Core_Model_Website $website */
            if ($this->_isConfigurationValueChanged($element, $website)) {

                $changedScopes[Mage::helper('scopehint')->__('Website: %s', $website->getName())] = $this->_getReadableConfigValue($website, $element);
            }

            foreach ($website->getStores() as $store) {

                /** @var Mage_Core_Model_Store $store */
                if ($this->_isConfigurationValueChanged($element, $store, $website)) {

                    $changedScopes[Mage::helper('scopehint')->__('Store View: %s', $this->_getFullStoreName($store))] = $this->_getReadableConfigValue($store, $element);
                }
            }
        }
        
        return $changedScopes;
    }

    /**
     * @param Varien_Data_Form_Element_Abstract $element
     * @param Mage_Core_Model_Store|Mage_Core_Model_Website $scope1
     * @param Mage_Core_Model_Website|null $scope2
     * @return bool
     */
    protected function _isConfigurationValueChanged($element, $scope1, $scope2 = null)
    {
        $scope1ConfigValue = $this->_getConfigValue($scope1, $element);
        $scope2ConfigValue = $this->_getConfigValue($scope2, $element);

        return ($scope1ConfigValue != $scope2ConfigValue);
    }

    /**
     * @param Mage_Core_Model_Store|Mage_Core_Model_Website|null $scope
     * @param Varien_Data_Form_Element_Abstract $element
     * @return string
     */
    protected function _getConfigValue($scope, $element)
    {
        $configCode = $this->_getConfigCode($element);

        if (is_null($scope)) {
            return (string)Mage::getConfig()->getNode('default/'.$configCode);
        } else {
            if ($scope instanceof Mage_Core_Model_Store) {

                return (string)Mage::getConfig()->getNode('stores/'.$scope->getCode().'/'.$configCode);
            } else if ($scope instanceof Mage_Core_Model_Website) {

                return (string)Mage::getConfig()->getNode('websites/'.$scope->getCode().'/'.$configCode);
            }
        }
    }

    /**
     * @param Mage_Core_Model_Store|Mage_Core_Model_Website|null $scope
     * @param Varien_Data_Form_Element_Abstract $element
     * @return string
     */
    protected function _getReadableConfigValue($scope, $element)
    {
        $rawValue = $this->_getConfigValue($scope, $element);
        $values = $element->getValues();
        if ($element->getType() == 'select') {

            if ($element->getExtType() == 'multiple') {

                $readableValues = array();
                $rawValues = explode(',', $rawValue);
                foreach($values as $value) {
                    if (in_array($value['value'], $rawValues)) {
                        $readableValues[] = $value['label'];
                    }
                }
                return implode(', ', $readableValues);
            } else {
                foreach($values as $value) {
                    if ($value['value'] == $rawValue) {
                        return $value['label'];
                    }
                }
            }
        }
        return $rawValue;
    }

    /**
     * @param array $changedScopes
     * @return string
     */
    protected function _getHintHtml($changedScopes)
    {
        $text = Mage::helper('scopehint')->__('Changes in:') . '<br />';

        foreach($changedScopes as $scope => $scopeValue) {

            $text .= $scope . ': ' . $scopeValue . '<br />';
        }

        $iconurl = Mage::getBaseUrl('skin') . 'adminhtml/default/default/images/error_msg_icon.gif';
        $html = '<img class="scopehint-icon" src="' . $iconurl . '" title="' . $text . '" alt="' . $text . '"/>';

        return $html;
    }

    /**
     * @param Mage_Core_Model_Store $store
     * @return string
     */
    protected function _getFullStoreName($store)
    {
        if (!isset($this->_fullStoreNames[$store->getId()])) {

            $fullStoreName = $store->getWebsite()->getName()
                             . ' / ' . $store->getGroup()->getName()
                             . ' / ' . $store->getName();
            $this->_fullStoreNames[$store->getId()] = $fullStoreName;
        }
        return $this->_fullStoreNames[$store->getId()];
    }

    /**
     * @return bool
     */
    protected function _isGlobalScope()
    {
        return (!$this->_isWebsiteScope() && !$this->_isStoreScope());
    }

    /**
     * @return bool
     */
    protected function _isWebsiteScope()
    {
        return (Mage::app()->getRequest()->getParam('website') && !$this->_isStoreScope());
    }

    /**
     * @return bool
     */
    protected function _isStoreScope()
    {
        return ((bool)Mage::app()->getRequest()->getParam('store'));
    }

    /**
     * @return Mage_Core_Model_Website
     */
    protected function getWebsite()
    {
        $websiteCode = Mage::app()->getRequest()->getParam('website');
        return Mage::app()->getWebsite($websiteCode);
    }

}
