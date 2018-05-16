<?php
/**
 * @category    Fishpig
 * @package     Fishpig_Wordpress
 * @license     http://fishpig.co.uk/license.txt
 * @author      Ben Tideswell <help@fishpig.co.uk>
 * @Obfuscate
 */

class Fishpig_Wordpress_Addon_Root_Helper_Data extends Fishpig_Wordpress_Helper_Abstract
{	
	/**
	 * @var bool
	**/
	static protected $_homepageIsReplaced = false;
	
	/**
	 * Determine whether @root is enabled
	 *
	 * @return bool
	 */
	public function isEnabled()
	{
		return Mage::getStoreConfigFlag('wordpress/integration/at_root', Mage::helper('wordpress/app')->getStore()->getId());
	}
	
	/**
	 * Determine whether to replace the Magento homepage with the WP homepage
	 *
	 * @return bool
	 */
	public function canReplaceHomepage()
	{
		return $this->isEnabled()
			&& Mage::getStoreConfigFlag('wordpress/integration/replace_homepage', Mage::helper('wordpress/app')->getStore()->getId());
	}	
	
	/**
	 * If can replace homepage, set the default route
	 *
	 * @param Varien_Event_Observer $observer
	 * @return $this
	 */
	public function controllerFrontInitObserver(Varien_Event_Observer $observer)
	{
		if ($this->isAdmin() || Mage::helper('wordpress')->isApiRequest()) {
			return false;
		}

		$app = Mage::app();

		if ('' === trim($app->getRequest()->getPathInfo(), '/')) {
			if ($app->getRequest()->getParam('preview') === 'true' && ($postId = $app->getRequest()->getParam('p'))) {
				$this->_setHomepageIsReplaced(true);
				$app->getStore()->setConfig('web/default/front', 'wordpress/post/view');
			}
			else if ($this->canReplaceHomepage()) {
				$this->_setHomepageIsReplaced(true);
				$app->getStore()->setConfig('web/default/front', 'wordpress');
			}
		}
		
		return $this;
	}
	
	/**
	 * @return $this
	**/
	protected function _setHomepageIsReplaced($flag)
	{
		self::$_homepageIsReplaced = (bool)$flag;
		
		return $this;
	}
	
	/**
	 * @return bool
	**/
	public function isHomepageReplaced()
	{
		return self::$_homepageIsReplaced;
	}
	
	/**
	 * Retrieve the blog route
	 * If Root enabled, set the blog route as an empty string
	 *
	 * @param Varien_Event_Observer $observer
	 * @return $this
	 */
	public function blogRouteGetObserver(Varien_Event_Observer $observer)
	{
		if ($this->isEnabled()) {
			$observer->getTransport()->setBlogRoute('');
		}
		
		return $this;
	}
	
	/**
	 * Retrieve the toplink URL
	 *
	 * @param Varien_Event_Observer $observer
	 * @return $this
	 */
	public function getToplinkUrlObserver(Varien_Event_Observer $observer)
	{
		if ($this->isEnabled() && ($url = $this->_getToplinkUrl()) !== null) {
			$observer->getEvent()
				->getTransport()
					->setToplinkUrl($url);
		}

		return $this;
	}
	
	/**
	 * @return string
	**/
	protected function _getToplinkUrl()
	{
		if ($this->isEnabled()) {
			if (($pageId = Mage::helper('wordpress/router')->getBlogPageId()) !== false) {
				$page = Mage::getModel('wordpress/post')->setPostType('page')->load($pageId);
				
				if ($page->getId()) {
					return $page->getUrl();
				}
			}
		}
		
		return null;
	}
	
	/**
	 * Retrieve the toplink URL
	 *
	 * @param Varien_Event_Observer $observer
	 * @return $this
	 */
	public function getToplinkLabelObserver(Varien_Event_Observer $observer)
	{
		if ($this->isEnabled() && !$this->canReplaceHomepage() && is_null($this->_getToplinkUrl())) {
			$observer->getEvent()
				->getTransport()
					->setToplinkLabel(null);
		}

		return $this;
	}
	
	/**
	 * Fix the breadcrumbs
	 *
	 * @param Varien_Event_Observer $observer
	 * @return $this
	 */
	public function initLayoutAfterObserver(Varien_Event_Observer $observer)
	{
		if (!$this->isEnabled() || $this->isAdmin()) {
			return $this;
		}

		$object = $observer->getEvent()->getObject();

		if ($object instanceof Fishpig_Wordpress_Model_Post) {
			if ($crumb = $observer->getEvent()->getController()->getCrumb('blog')) {
				if (!isset($crumb[0]) || !isset($crumb[0]['link'])) {
					return $this;
				}

				if ($blogUri = Mage::helper('wordpress/router')->getBlogUri()) {
					if (strpos($blogUri, '/') !== false) {
						$crumbLink = rtrim($crumb[0]['link'], '/');
						$crumbLink = substr($crumbLink, strrpos($crumbLink, '/')+1);
						
						if ($crumbLink !== substr($blogUri, 0, strpos($blogUri, '/'))) {
							$observer->getEvent()->getController()->removeCrumb('blog');
						}
					}
					else {
						$observer->getEvent()->getController()->removeCrumb('blog');
					}
				}
			}
		}
		else {
			$observer->getEvent()->getController()->removeCrumb('blog');
		}
		
		return $this;
	}

	/**
	 * @return string
	**/
	public function cmsPageRenderObserver(Varien_Event_Observer $observer)
	{
		if ($observer->getEvent()->getPage()->getIdentifier() !== Mage::getStoreConfig('web/default/cms_home_page')) {
			return $this;
		}
		
		$_request = Mage::app()->getRequest();
		$_response = Mage::app()->getResponse();
		
		if ($_request->getParam('preview') || (int)$_request->getParam('preview_id') > 0) {
			$_response->clearBody();
			$_response->setRedirect(Mage::getUrl('wordpress/post/preview', array('_query' => $_request->getParams())));
			$_response->sendHeaders();
			exit;
		}
	}

	/**
	 * Determine whether request is Admin request
	 *
	 * @return bool
	 */
	public function isAdmin()
	{
		$adminFrontName = Mage::getConfig()->getNode('admin/routers/adminhtml/args/frontName');
		$pathInfo = Mage::app()->getRequest()->getPathInfo();
		
		return (strpos($pathInfo, '/' . $adminFrontName . '/') === 0)
			|| ('/' . $adminFrontName === rtrim($pathInfo));
	}
}
