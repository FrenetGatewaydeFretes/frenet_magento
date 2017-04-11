<?php
/**
 * This source file is subject to the MIT License.
 * It is also available through http://opensource.org/licenses/MIT
 *
 * @category  Akhilleus
 * @package   LithiumSoftware_Akhilleus
 * @author    LithiumSoftware <contato@lithiumsoftware.com.br>
 * @copyright 2015 Lithium Software
 * @license   http://opensource.org/licenses/MIT MIT
 */

/** @var $installer Mage_Core_Model_Resource_Setup */
$installer = $this;
$installer->startSetup();

/* @var $installer Mage_Catalog_Model_Resource_Eav_Mysql4_Setup */
$setup = new Mage_Eav_Model_Entity_Setup('core_setup');

// Add volume to product attribute set
$codigo = 'volume_comprimento';
$config = array(
    'position' => 1,
    'required' => 1,
    'label'    => 'Comprimento (cm)',
    'type'     => 'int',
    'input'    => 'text',
    'apply_to' => 'simple,bundle,grouped,configurable',
    'default'  => 16,
    'note'     => 'Comprimento da embalagem do produto (Para cálculo de frete, mínimo de 16cm)'
);

$setup->addAttribute('catalog_product', $codigo, $config);

// Add volume to product attribute set
$codigo = 'volume_altura';
$config = array(
    'position' => 1,
    'required' => 1,
    'label'    => 'Altura (cm)',
    'type'     => 'int',
    'input'    => 'text',
    'apply_to' => 'simple,bundle,grouped,configurable',
    'default'  => 2,
    'note'     => 'Altura da embalagem do produto (Para cálculo de frete, mínimo de 2cm)'
);

$setup->addAttribute('catalog_product', $codigo, $config);

// Add volume to product attribute set
$codigo = 'volume_largura';
$config = array(
    'position' => 1,
    'required' => 1,
    'label'    => 'Largura (cm)',
    'type'     => 'int',
    'input'    => 'text',
    'apply_to' => 'simple,bundle,grouped,configurable',
    'default'  => 11,
    'note'     => 'Largura da embalagem do produto (Para cálculo de frete, mínimo de 11cm)'
);

$setup->addAttribute('catalog_product', $codigo, $config);

// Add leadtime to product attribute set
$codigo = 'leadtime';
$config = array(
    'position' => 1,
    'required' => 1,
    'label'    => 'Lead time (dias)',
    'type'     => 'int',
    'input'    => 'text',
    'apply_to' => 'simple,bundle,grouped,configurable',
    'default'  => 0,
    'note'     => 'Tempo de fabricação do produto (Para cálculo de frete)'
);

$setup->addAttribute('catalog_product', $codigo, $config);

// Add fragile to product attribute set
$codigo = 'fragile';
$config = array(
    'position' => 1,
    'label'    => 'Produto frágil?',
    'type'     => 'int',
    'input'    => 'boolean',
    'apply_to' => 'simple,bundle,grouped,configurable',
    'default'  => 0,
    'required' => 1,
    'note'     => 'Produto contém vidro ou outros materiais frágeis? (Para cálculo de frete)'
);

$setup->addAttribute('catalog_product', $codigo, $config);

$installer->endSetup();
