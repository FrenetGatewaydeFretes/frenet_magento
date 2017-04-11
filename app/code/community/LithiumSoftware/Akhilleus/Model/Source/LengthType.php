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
class LithiumSoftware_Akhilleus_Model_Source_LengthType
{
    /**
     * Constants for weight
     */
    const LENGTH_MM = 'mm';
    const LENGTH_CM = 'cm';
    const LENGTH_M = 'm';

    /**
     * Get options for weight
     *
     * @return array
     */
    public function toOptionArray()
    {
        return array(
            array('value' => self::LENGTH_MM, 'label' => Mage::helper('adminhtml')->__('MILIMETROS')),
            array('value' => self::LENGTH_CM, 'label' => Mage::helper('adminhtml')->__('CENTIMETROS')),
            array('value' => self::LENGTH_M, 'label' => Mage::helper('adminhtml')->__('METROS')),
        );
    }
}
