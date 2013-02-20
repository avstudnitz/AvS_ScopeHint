<?php
class AvS_ScopeHint_Block_Hint extends Mage_Adminhtml_Block_Abstract
{
    /** @var Varien_Data_Form_Element_Abstract */
    protected $_element = null;

    /** @var array */
    protected $_fullStoreNames = array();

    /**
     * @param Varien_Data_Form_Element_Abstract $element
     * @return AvS_ScopeHint_Block_Hint
     */
    public function setElement($element)
    {
        $this->_element = $element;
        return $this;
    }

    /**
     * @return Varien_Data_Form_Element_Abstract
     */
    public function getElement()
    {
        return $this->_element;
    }

    /**
     * @return string
     */
    protected function _toHtml()
    {
        $changedScopes = array();

        if ($this->_isStoreScope()) return '';

        if ($this->_isWebsiteScope()) {

            $website = $this->getWebsite();
            $changedScopes = $this->_getChangedStoresForWebsite($website);
        }

        if ($this->_isGlobalScope()) {

            $changedScopes = $this->_getChangedScopesForGlobal();
        }

        if (empty($changedScopes)) return '';

        return $this->_getHintHtml($changedScopes);
    }

    /**
     * @return string
     */
    protected function _getConfigCode()
    {
        $configCode = preg_replace('#\[value\](\[\])?$#', '', $this->getElement()->getName());
        $configCode = str_replace('[fields]', '', $configCode);
        $configCode = str_replace('groups[', '[', $configCode);
        $configCode = str_replace('][', '/', $configCode);
        $configCode = str_replace(']', '', $configCode);
        $configCode = str_replace('[', '', $configCode);
        $configCode = Mage::app()->getRequest()->getParam('section') . '/' . $configCode;
        return $configCode;
    }

    /**
     * @param Mage_Core_Model_Website $website
     * @return array
     */
    protected function _getChangedStoresForWebsite($website)
    {
        $changedStores = array();

        foreach ($website->getStores() as $store) {

            /** @var Mage_Core_Model_Store $store */
            if ($this->_isConfigurationValueChanged($store, $website)) {

                $changedStores[Mage::helper('scopehint')->__('Store View: %s', $this->_getFullStoreName($store))] = $this->_getReadableConfigValue($store, $element);
            }
        }
        return $changedStores;
    }

    /**
     * @return array
     */
    protected function _getChangedScopesForGlobal()
    {
        $changedScopes = array();

        foreach (Mage::app()->getWebsites() as $website) {

            /** @var Mage_Core_Model_Website $website */
            if ($this->_isConfigurationValueChanged($website)) {

                $changedScopes[Mage::helper('scopehint')->__('Website: %s', $website->getName())] = $this->_getReadableConfigValue($website);
            }

            foreach ($website->getStores() as $store) {

                /** @var Mage_Core_Model_Store $store */
                if ($this->_isConfigurationValueChanged($store, $website)) {

                    $changedScopes[Mage::helper('scopehint')->__('Store View: %s', $this->_getFullStoreName($store))] = $this->_getReadableConfigValue($store);
                }
            }
        }

        return $changedScopes;
    }

    /**
     * @param Mage_Core_Model_Store|Mage_Core_Model_Website $scope1
     * @param Mage_Core_Model_Website|null $scope2
     * @return bool
     */
    protected function _isConfigurationValueChanged($scope1, $scope2 = null)
    {
        $scope1ConfigValue = $this->_getConfigValue($scope1);
        $scope2ConfigValue = $this->_getConfigValue($scope2);

        return ($scope1ConfigValue != $scope2ConfigValue);
    }

    /**
     * @param Mage_Core_Model_Store|Mage_Core_Model_Website|null $scope
     * @return string
     */
    protected function _getConfigValue($scope)
    {
        $configCode = $this->_getConfigCode();

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
     * @return string
     */
    protected function _getReadableConfigValue($scope)
    {
        $rawValue = $this->_getConfigValue($scope);
        $values = $this->getElement()->getValues();
        if ($this->getElement()->getType() == 'select') {

            if ($this->getElement()->getExtType() == 'multiple') {

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