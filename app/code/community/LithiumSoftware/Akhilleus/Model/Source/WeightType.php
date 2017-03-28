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
class LithiumSoftware_Akhilleus_Model_Source_WeightType
{
    /**
     * Constants for weight
     */
    const WEIGHT_GR = 'gr';
    const WEIGHT_KG = 'kg';

    /**
     * Get options for weight
     *
     * @return array
     */
    public function toOptionArray()
    {
        return array(
            array('value' => self::WEIGHT_GR, 'label' => Mage::helper('adminhtml')->__('Gramas')),
            array('value' => self::WEIGHT_KG, 'label' => Mage::helper('adminhtml')->__('Kilos')),
        );
    }
}
