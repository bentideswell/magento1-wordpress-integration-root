<?php
/**
 * @category    Fishpig
 * @package     Fishpig_Wordpress
 * @license     http://fishpig.co.uk/license.txt
 * @author      Ben Tideswell <help@fishpig.co.uk>
 */
 
class Fishpig_Wordpress_Addon_Root_Helper_MC_Router extends Mana_Core_Helper_Router
{
	/**
	 * @var bool
	**/
	protected $_homepageFlag = null;
	
	/**
	 * @return $this
	**/
	public function forward($route, $request = null, $params = null, $query = null)
	{
		if (!$this->isHomepageReplaced()) {
			return parent::forward($route, $request, $params, $query);
		}

		return $this;
	}
	
	/**
	 * @return $this
	**/
	public function changePath($path, $request = null)
	{
		if (!$this->isHomepageReplaced()) {
			return parent::changePath($path, $request);
		}
		
		return $this;
	}
	
	/**
	 * @return $this
	**/
	public function isHomepageReplaced()
	{
		if (is_null($this->_homepageFlag)) {
			$this->_homepageFlag = Mage::helper('wp_addon_root')->isHomepageReplaced();
		}
		
		return $this->_homepageFlag;
	}
}
