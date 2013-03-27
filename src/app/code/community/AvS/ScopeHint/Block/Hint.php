<?php
/**
 * @method Varien_Data_Form_Element_Abstract getElement()
 * @method AvS_ScopeHint_Block_Hint setElement(Varien_Data_Form_Element_Abstract $element)
 * @method string getType()
 * @method AvS_ScopeHint_Block_Hint setType(string $type)
 */

class AvS_ScopeHint_Block_Hint extends Mage_Adminhtml_Block_Abstract
{
    /** @var array */
    protected $_fullStoreNames = array();

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
            if ($this->_isValueChanged($store, $website)) {

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

        switch ($this->getType()) {
            case 'config':

                foreach (Mage::app()->getWebsites() as $website) {

                    /** @var Mage_Core_Model_Website $website */
                    if ($this->_isValueChanged($website)) {

                        $changedScopes[Mage::helper('scopehint')->__('Website: %s', $website->getName())] = $this->_getReadableConfigValue($website);
                    }

                    foreach ($website->getStores() as $store) {

                        /** @var Mage_Core_Model_Store $store */
                        if ($this->_isValueChanged($store, $website)) {

                            $changedScopes[Mage::helper('scopehint')->__('Store View: %s', $this->_getFullStoreName($store))] = $this->_getReadableConfigValue($store);
                        }
                    }
                }
                break;

            case 'product':
            case 'category':

                foreach (Mage::app()->getStores() as $store) {

                    /** @var Mage_Core_Model_Store $store */
                    if ($this->_isValueChanged($store)) {

                        $changedScopes[Mage::helper('scopehint')->__('Store View: %s', $this->_getFullStoreName($store))] = $this->_getReadableConfigValue($store);
                    }
                }
                break;
        }

        return $changedScopes;
    }

    /**
     * @param Mage_Core_Model_Store|Mage_Core_Model_Website $scope1
     * @param Mage_Core_Model_Website|null $scope2
     * @return bool
     */
    protected function _isValueChanged($scope1, $scope2 = null)
    {
        if ($this->getType() != 'config' && $scope1 instanceof Mage_Core_Model_Website) {
            // products and categories don't have a website scope
            return false;
        }
        $scope1ConfigValue = $this->_getValue($scope1);
        $scope2ConfigValue = $this->_getValue($scope2);

        return ($scope1ConfigValue != $scope2ConfigValue);
    }

    /**
     * @param Mage_Core_Model_Store|Mage_Core_Model_Website|null $scope
     * @return string
     */
    protected function _getValue($scope)
    {
        switch ($this->getType()) {

            case 'config':
                $configCode = $this->_getConfigCode();

                if (is_null($scope)) {
                    return (string)Mage::getConfig()->getNode('default/'.$configCode);
                } else if ($scope instanceof Mage_Core_Model_Store) {
                    return (string)Mage::getConfig()->getNode('stores/'.$scope->getCode().'/'.$configCode);
                } else if ($scope instanceof Mage_Core_Model_Website) {
                    return (string)Mage::getConfig()->getNode('websites/'.$scope->getCode().'/'.$configCode);
                }
                break;

            case 'product':
                $attributeName = $this->getElement()->getData('name');
                if (is_null($scope)) {
                    return (string)$this->_getProduct()->getData($attributeName);
                } else if ($scope instanceof Mage_Core_Model_Store) {
                    return (string)$this->_getProduct($scope)->getData($attributeName);
                }
                break;

            case 'category':
                $attributeName = $this->getElement()->getData('name');
                if (is_null($scope)) {
                    return (string)$this->_getCategory()->getData($attributeName);
                } else if ($scope instanceof Mage_Core_Model_Store) {
                    return (string)$this->_getCategory($scope)->getData($attributeName);
                }
                break;
        }
    }

    /**
     * @param Mage_Core_Model_Store $store
     * @return Mage_Catalog_Model_Product
     */
    protected function _getProduct(Mage_Core_Model_Store $store = null)
    {
        if (is_null($store)) {
            $storeId = 0;
        } else {
            $storeId = $store->getId();
        }

        if (is_null(Mage::registry('product_' . $storeId))) {
            /** @var $product Mage_Catalog_Model_Product */
            $product = Mage::getModel('catalog/product');
            $product->setStoreId($storeId);
            Mage::register('product_' . $storeId, $product->load($this->getEntityId()));
        }

        return Mage::registry('product_' . $storeId);
    }

    /**
     * @param Mage_Core_Model_Store $store
     * @return Mage_Catalog_Model_Category
     */
    protected function _getCategory(Mage_Core_Model_Store $store = null)
    {
        if (is_null($store)) {
            $storeId = 0;
        } else {
            $storeId = $store->getId();
        }

        if (is_null(Mage::registry('category_' . $storeId))) {
            /** @var $category Mage_Catalog_Model_Category */
            $category = Mage::getModel('catalog/category');
            $category->setStoreId($storeId);
            Mage::register('category_' . $storeId, $category->load($this->getEntityId()));
        }

        return Mage::registry('category_' . $storeId);
    }

    /**
     * @param Mage_Core_Model_Store|Mage_Core_Model_Website|null $scope
     * @return string
     */
    protected function _getReadableConfigValue($scope)
    {
        $rawValue = $this->_getValue($scope);
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

            $text .= $this->escapeHtml($scope). ':'
                . '<br />'
                . nl2br(wordwrap($this->escapeHtml($scopeValue)))
                . '<br />';
        }

        $iconurl = Mage::getBaseUrl('skin') . 'adminhtml/default/default/images/note_msg_icon.gif';
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

    /**
     * @return int
     */
    protected function getEntityId()
    {
        if ($this->getType() == 'product') {
            return intval($this->getRequest()->getParam('id'));
        } else {
            return Mage::registry('current_category')->getId();
        }
    }

}