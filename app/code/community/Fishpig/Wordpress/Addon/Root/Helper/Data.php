<?php
/**
 * @category    Fishpig
 * @package     Fishpig_Wordpress
 * @license     http://fishpig.co.uk/license.txt
 * @author      Ben Tideswell <help@fishpig.co.uk>
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

			if ($postId = $this->getPreviewId()) {
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
	
	protected function getPreviewId()
	{
		if (!($params = Mage::app()->getRequest()->getParams())) {
			return false;
		}
		
		if (strpos(implode('-', array_keys($params)), 'preview') === false) {
			return false;
		}
		
		$paramKeys = array(
			'p',
			'page_id',
			'elementor-preview',
		);
		
		foreach($paramKeys as $paramKey) {
			if ($postId = (int)Mage::app()->getRequest()->getParam($paramKey)) {
				return $postId;
			}
		}
		
		return false;
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
	
	/*
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
	
	/*
	 * Setup breadrcrumbs
	 *
	 * @param Varien_Event_Observer $observer
	 * @return $this
	 */

	public function wordpressBreadcrumbsGetAfterObserver(Varien_Event_Observer $observer)
	{
		if (!$this->isEnabled()) {
			return false;
		}
		
		$crumbs = $observer->getEvent()->getTransport()->getCrumbs();
		$object = $observer->getEvent()->getObject();

		unset($crumbs['blog_home']);
				
		if ($this->canReplaceHomepage()) {
			unset($crumbs['home']);			
		}

		$observer->getEvent()->getTransport()->setCrumbs($crumbs);

		return $this;
	}
	
	/*
	 *
	 * @return string
	 */
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
