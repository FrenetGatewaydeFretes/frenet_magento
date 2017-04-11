<?php

/** @var $installer Mage_Core_Model_Resource_Setup */
$installer = $this;
$installer->startSetup();

/* @var $installer Mage_Catalog_Model_Resource_Eav_Mysql4_Setup */
$setup = new Mage_Eav_Model_Entity_Setup('core_setup');

$setup->updateAttribute('catalog_product', 'leadtime','is_required', 0);
$setup->updateAttribute('catalog_product', 'fragile','is_required', 0);

$installer->endSetup();
