<?
App::uses('AppController', 'Controller');

App::import('Vendor', 'GoogleShopping', array('file' => 'google-shopping-feed/vendor/autoload.php'));
App::import('Vendor', 'GoogleShopping', array('file' => 'google-shopping-feed/src/LukeSnowden/GoogleShoppingFeed/Containers/GoogleShopping.php'));

use LukeSnowden\GoogleShoppingFeed\Containers\GoogleShopping;

class ProductotiendasController extends AppController {
 
    public $name = 'Productotiendas';    
    public $uses = array('Productotienda');
    public $helpers = array('Text');
    private $formatosGoogle = array(
    	'json',
    	'xml'
    );



    function beforeFilter() {
	    parent::beforeFilter();

	    # Se permite el acceso por url al método feed
	    if (isset($this->request->params['knasta'])) {
	    	$this->Auth->allow('feed');	
	    }
	}


	/**
	 * Ordena la información de la categoria
	 * @param 	$id_category 		int 		Identificador de categoia
	 * @param 	$name 				string 		Nombre de la categoria
	 * @param 	$level_depth 		int 		Nivel de categoria
	 * @param 	$parent_categories	array 		Arreglo de datos de categorias padres
	 * @return 	$array 
	 */
	public function formatTree($id_category = null, $name = '', $level_depth = null, $parent_categories = array())
	{
		$arr = array(
			'id_category' => (int) $id_category,
			'name' => (string) $name,
			'level_depth' => (int) $level_depth,
			'parent_categories' => (array) $parent_categories
		);

		return $arr;
	}


	public function getParentCategory($id_category = '', $prefix = 'tm_')
	{
		if (empty($id_category)) {
			return;
		}

		$categories = array();

		$q = "SELECT c.id_category, c.id_parent, c.level_depth, cl.name FROM  ".$prefix."category AS c 
			LEFT JOIN ".$prefix."category_lang cl ON (c.id_category = cl.id_category) 
			WHERE c.id_category =" . $id_category;
			
		$parentCategory = $this->Productotienda->query($q);
		
		for ( $i = $parentCategory[0]['c']['level_depth'] ; $i > 1 ; $i--) {
			if ($i == $parentCategory[0]['c']['level_depth']) {
				$categories[$i] = $this->formatTree($parentCategory[0]['c']['id_category'], $parentCategory[0]['cl']['name'], $parentCategory[0]['c']['level_depth'], $this->getParentCategory($parentCategory[0]['c']['id_parent'], $prefix));	
			}
		}
	
		return $categories;
	
	}

	public function categoriesTree( $categories = array() )
	{
		$arr = '';
		
		foreach ($categories as $ix => $category) {
			$arr .= $category['name'] . ',';
			
			for ( $i = $category['level_depth']; $i > 0 ; $i-- ) { 
				if ($i == $category['level_depth'] ) {
					$arr .= $this->categoriesTree($category['parent_categories']);
				}
			}
		}

		return $arr;
	}

	public function tree($string)
	{
		$formatted = explode(',', $string);
		$formatted = array_reverse($formatted);
		
		$s = '';

		foreach ($formatted as $key => $value) {
			if ($key > 1) {
				$s .= ' > ' . $value;
			}else{
				$s .= $value;
			}
		}

		return $s;
	}


	/**
	 * Genera un JSON con todos los productos de la tienda seleccionada 
	 * por la url.
	 * @return json 
	 */
    public function knasta_feed()
    {	
    	$out = array();

    	if (isset($this->request->params['knasta']) && isset($this->request->params['tienda']) ) 
		{
			//Buscamos el prefijo de la tienda
			$tienda = ClassRegistry::init('Tienda')->find('first', array(
			'conditions' => array(
				'Tienda.configuracion' => $this->request->params['tienda']
				)
			));
		}else{

			$out = array('error' => array('code' => 400, 'message' => 'Tienda no válida'));
		
		}

		// Virificar existencia de la tienda
		if (empty($tienda)) {
			$out = array('error' => array('code' => 404, 'message' => 'Tienda no válida'));	
		}else if (empty($tienda['Tienda']['prefijo']) || empty($tienda['Tienda']['prefijo']) || empty($tienda['Tienda']['configuracion']) || empty($tienda['Tienda']['url'])) {
			$out = array('error' => array('code' => 500, 'message' => 'La tienda no está configurada completamente. Verifiquela y vuelva a intentarlo'));
		}else{
			# Url de la tienda
			$sitioUrl = $this->formatear_url($tienda['Tienda']['url'], true);
			
			# cambiamos el datasource de las modelos externos
			$this->cambiarConfigDB($tienda['Tienda']['configuracion']);
	
			// Buscamos los productos que cumplan con el criterio
			$productos	= $this->Productotienda->find('all', array(
				'fields' => array(
					'concat(\'http://' . $tienda['Tienda']['url'] . '/img/p/\',mid(im.id_image,1,1),\'/\', if (length(im.id_image)>1,concat(mid(im.id_image,2,1),\'/\'),\'\'),if (length(im.id_image)>2,concat(mid(im.id_image,3,1),\'/\'),\'\'),if (length(im.id_image)>3,concat(mid(im.id_image,4,1),\'/\'),\'\'),if (length(im.id_image)>4,concat(mid(im.id_image,5,1),\'/\'),\'\'), im.id_image, \'-home_default.jpg\' ) AS url_image_thumb',
					'concat(\'http://' . $tienda['Tienda']['url'] . '/img/p/\',mid(im.id_image,1,1),\'/\', if (length(im.id_image)>1,concat(mid(im.id_image,2,1),\'/\'),\'\'),if (length(im.id_image)>2,concat(mid(im.id_image,3,1),\'/\'),\'\'),if (length(im.id_image)>3,concat(mid(im.id_image,4,1),\'/\'),\'\'),if (length(im.id_image)>4,concat(mid(im.id_image,5,1),\'/\'),\'\'), im.id_image, \'.jpg\' ) AS url_image_large',
					'Productotienda.id_product',
					'Productotienda.id_category_default',
					'pl.name', 
					'pl.description_short',
					'Productotienda.price', 
					'pl.link_rewrite', 
					'Productotienda.reference', 
					'Productotienda.show_price',
					'Productotienda.quantity'
				),
				'joins' => array(
					array(
			            'table' => sprintf('%sproduct_lang', $tienda['Tienda']['prefijo']),
			            'alias' => 'pl',
			            'type'  => 'LEFT',
			            'conditions' => array(
			                'Productotienda.id_product=pl.id_product'
			            )

		        	),
		        	array(
			            'table' => sprintf('%simage', $tienda['Tienda']['prefijo']),
			            'alias' => 'im',
			            'type'  => 'LEFT',
			            'conditions' => array(
			                'Productotienda.id_product = im.id_product',
	                		'im.cover' => 1
			            )
		        	),
		        	array(
			            'table' => sprintf('%scategory_product', $tienda['Tienda']['prefijo']),
			            'alias' => 'CategoriaProducto',
			            'type'  => 'LEFT',
			            'conditions' => array(
			                'CategoriaProducto.id_product' => 'Productotienda.id_product'
			            )
		        	)
				),
				'contain' => array(
					'TaxRulesGroup' => array(
						'TaxRule' => array(
							'Tax'
						)
					),
					'SpecificPrice' => array(
						'conditions' => array(
							'OR' => array(
								array(
									'SpecificPrice.from <= "' . date('Y-m-d H:i:s') . '"',
									'SpecificPrice.to >= "' . date('Y-m-d H:i:s') . '"'
								),
								array(
									'SpecificPrice.from' => '0000-00-00 00:00:00',
									'SpecificPrice.to >= "' . date('Y-m-d H:i:s') . '"'
								),
								array(
									'SpecificPrice.from' => '0000-00-00 00:00:00',
									'SpecificPrice.to' => '0000-00-00 00:00:00'
								),
								array(
									'SpecificPrice.from <= "' . date('Y-m-d H:i:s') . '"',
									'SpecificPrice.to' => '0000-00-00 00:00:00'
								)
							)
						)
					),
					'SpecificPricePriority'
				),
				'conditions' => array(
					'Productotienda.active' => 1,
					'Productotienda.available_for_order' => 1,
					'Productotienda.id_shop_default' => 1
				)
			));
			
			$knasta = array();
			
			foreach ($productos as $key => $value) {
				$cate = $this->getParentCategory($value['Productotienda']['id_category_default'], $tienda['Tienda']['prefijo']);
				$cate = $this->categoriesTree($cate);


				if ( !isset($value['TaxRulesGroup']['TaxRule'][0]['Tax']['rate']) ) {
					$value['Productotienda']['valor_iva'] = $value['Productotienda']['price'];	
				}else{
					$value['Productotienda']['valor_iva'] = $this->precio($value['Productotienda']['price'], $value['TaxRulesGroup']['TaxRule'][0]['Tax']['rate']);
				}
				

				// Criterio del precio específico del producto
				foreach ($value['SpecificPricePriority'] as $criterio) {
					$precioEspecificoPrioridad = explode(';', $criterio['priority']);
				}

				$value['Productotienda']['valor_final'] = $value['Productotienda']['valor_iva'];

				// Retornar último precio espeficico según criterio del producto
				foreach ($value['SpecificPrice'] as $precio) {
					if ( $precio['reduction'] == 0 ) {
						$value['Productotienda']['valor_final'] = $value['Productotienda']['valor_iva'];

					}else{

						$value['Productotienda']['valor_final'] = $this->precio($value['Productotienda']['valor_iva'], ($precio['reduction'] * 100 * -1) );
						$value['Productotienda']['descuento'] = ($precio['reduction'] * 100 * -1 );

					}
				}

				$knasta[$key]['Sku'] = $value['Productotienda']['reference'];
				$knasta[$key]['Description'] = strip_tags($value['pl']['description_short']);
				$knasta[$key]['Title'] = $value['pl']['name'];
				$knasta[$key]['ProductListImage'] = $value[0]['url_image_thumb'];
				$knasta[$key]['ProductViewImages'] = array($value[0]['url_image_large']);
				$knasta[$key]['ProductUrl'] = sprintf('%s%s-%s.html', $sitioUrl, $value['pl']['link_rewrite'], $value['Productotienda']['id_product']);;
				$knasta[$key]['CategoryId'] = $value['Productotienda']['id_category_default'];
				$knasta[$key]['CategoryName'] = $this->tree($cate);
				$knasta[$key]['Stock'] = '1';
				#$knasta[$key]['Stock'] = ($value['Productotienda']['quantity'] > 0) ? '1' : '0';
				$knasta[$key]['InternetPrice'] = CakeNumber::currency($value['Productotienda']['valor_final'], 'CLP');

			}

			$out = $knasta;
		}

		$this->layout = 'ajax';
		
		$out = str_replace('"', '\\\"', $out);
		
		header('Content-Type: application/json; charset=utf-8'); 
		echo json_encode($out, JSON_UNESCAPED_UNICODE);
		exit;

		$this->set(compact('out'));
		$this->set('_serialize', array('out'));
    	
    }


    /**
     * Reemplaza los carácteres no permitiods en un XML
     * @param  [type] $cadena [description]
     * @return [type]         [description]
     */
    public function limpioCaracteresXML($cadena){
	    $search  = array("<", ">", "&", "'");
	    $replace = array("&lt;", "&gt", "&amp;", "&apos");
	    $final = str_replace($search, $replace, $cadena);
	    return $final;
	}



	/**
	 * Genera XML con todos los productos de la tienda seleccionada por URL,
	 * @return XML  impreso en pantalla.
	 */
    public function google_feed()
    {
    	$out = array();
    	$tienda = array();
    	if (!isset($this->request->params['google']) || !isset($this->request->params['tienda']) ) 
		{
			
			$out = array('error' => array('code' => 400, 'message' => 'Tienda o formato no válido'));
			header('Content-Type: application/json; charset=utf-8'); 
			echo json_encode($out, JSON_UNESCAPED_UNICODE);
			exit;

		}else{

			//Buscamos el prefijo de la tienda
			$tienda = ClassRegistry::init('Tienda')->find('first', array(
			'conditions' => array(
				'Tienda.configuracion' => $this->request->params['tienda']
				)
			));
		}

		// Virificar existencia de la tienda
		if (empty($tienda['Tienda']['prefijo']) || empty($tienda['Tienda']['prefijo']) || empty($tienda['Tienda']['configuracion']) || empty($tienda['Tienda']['url'])) {
			$out = array('error' => array('code' => 500, 'message' => 'La tienda no está configurada completamente. Contacte al administrador del sistema.'));

			header('Content-Type: application/json; charset=utf-8'); 
			echo json_encode($out, JSON_UNESCAPED_UNICODE);
			exit;

		}else{
			# Url de la tienda
			$sitioUrl = $this->formatear_url($tienda['Tienda']['url'], true);
			
			# cambiamos el datasource de las modelos externos
			$this->cambiarConfigDB($tienda['Tienda']['configuracion']);
	
			// Buscamos los productos que cumplan con el criterio
			$productos	= $this->Productotienda->find('all', array(
				'fields' => array(
					'concat(\'https://' . $tienda['Tienda']['url'] . '/img/p/\',mid(im.id_image,1,1),\'/\', if (length(im.id_image)>1,concat(mid(im.id_image,2,1),\'/\'),\'\'),if (length(im.id_image)>2,concat(mid(im.id_image,3,1),\'/\'),\'\'),if (length(im.id_image)>3,concat(mid(im.id_image,4,1),\'/\'),\'\'),if (length(im.id_image)>4,concat(mid(im.id_image,5,1),\'/\'),\'\'), im.id_image, \'-home_default.jpg\' ) AS url_image_thumb',
					'concat(\'https://' . $tienda['Tienda']['url'] . '/img/p/\',mid(im.id_image,1,1),\'/\', if (length(im.id_image)>1,concat(mid(im.id_image,2,1),\'/\'),\'\'),if (length(im.id_image)>2,concat(mid(im.id_image,3,1),\'/\'),\'\'),if (length(im.id_image)>3,concat(mid(im.id_image,4,1),\'/\'),\'\'),if (length(im.id_image)>4,concat(mid(im.id_image,5,1),\'/\'),\'\'), im.id_image, \'.jpg\' ) AS url_image_large',
					'Productotienda.id_product',
					'Productotienda.id_category_default',
					'pl.name', 
					'pl.description_short',
					'Productotienda.price', 
					'pl.link_rewrite', 
					'Productotienda.reference', 
					'Productotienda.show_price',
					'Productotienda.quantity',
					'Productotienda.id_manufacturer',
					'Productotienda.condition',
					'Productotienda.supplier_reference',
					'Marca.id_manufacturer',
					'Marca.name'
				),
				'joins' => array(
					array(
			            'table' => sprintf('%sproduct_lang', $tienda['Tienda']['prefijo']),
			            'alias' => 'pl',
			            'type'  => 'LEFT',
			            'conditions' => array(
			                'Productotienda.id_product=pl.id_product'
			            )

		        	),
		        	array(
			            'table' => sprintf('%simage', $tienda['Tienda']['prefijo']),
			            'alias' => 'im',
			            'type'  => 'LEFT',
			            'conditions' => array(
			                'Productotienda.id_product = im.id_product',
	                		'im.cover' => 1
			            )
		        	),
		        	array(
			            'table' => sprintf('%scategory_product', $tienda['Tienda']['prefijo']),
			            'alias' => 'CategoriaProducto',
			            'type'  => 'LEFT',
			            'conditions' => array(
			                'CategoriaProducto.id_product' => 'Productotienda.id_product'
			            )
		        	),
		        	array(
			            'table' => sprintf('%smanufacturer', $tienda['Tienda']['prefijo']),
			            'alias' => 'Marca',
			            'type'  => 'LEFT',
			            'conditions' => array(
			                'Productotienda.id_manufacturer = Marca.id_manufacturer'
			            )
		        	)
				),
				'contain' => array(
					'TaxRulesGroup' => array(
						'TaxRule' => array(
							'Tax'
						)
					),
					'SpecificPrice' => array(
						'conditions' => array(
							'OR' => array(
								array(
									'SpecificPrice.from <= "' . date('Y-m-d H:i:s') . '"',
									'SpecificPrice.to >= "' . date('Y-m-d H:i:s') . '"'
								),
								array(
									'SpecificPrice.from' => '0000-00-00 00:00:00',
									'SpecificPrice.to >= "' . date('Y-m-d H:i:s') . '"'
								),
								array(
									'SpecificPrice.from' => '0000-00-00 00:00:00',
									'SpecificPrice.to' => '0000-00-00 00:00:00'
								),
								array(
									'SpecificPrice.from <= "' . date('Y-m-d H:i:s') . '"',
									'SpecificPrice.to' => '0000-00-00 00:00:00'
								)
							)
						)
					),
					'SpecificPricePriority'
				),
				'conditions' => array(
					'Productotienda.active' => 1,
					'Productotienda.available_for_order' => 1,
					'Productotienda.id_shop_default' => 1
				)
			));


			# Feed de Google
			GoogleShopping::title('Feed Google Shopping');
			GoogleShopping::link(FULL_BASE_URL);
			GoogleShopping::description('Feed generado por Nodriza Spa [cristian.rojas@nodriza.cl]');
			GoogleShopping::setIso4217CountryCode('CLP');

			
			$google = array();

			foreach ($productos as $key => $value) {
				$cate = $this->getParentCategory($value['Productotienda']['id_category_default'], $tienda['Tienda']['prefijo']);
				$cate = $this->categoriesTree($cate);


				if ( !isset($value['TaxRulesGroup']['TaxRule'][0]['Tax']['rate']) ) {
					$value['Productotienda']['valor_iva'] = $value['Productotienda']['price'];	
				}else{
					$value['Productotienda']['valor_iva'] = $this->precio($value['Productotienda']['price'], $value['TaxRulesGroup']['TaxRule'][0]['Tax']['rate']);
				}
				

				// Criterio del precio específico del producto
				foreach ($value['SpecificPricePriority'] as $criterio) {
					$precioEspecificoPrioridad = explode(';', $criterio['priority']);
				}

				$value['Productotienda']['valor_final'] = $value['Productotienda']['valor_iva'];

				// Retornar último precio espeficico según criterio del producto
				foreach ($value['SpecificPrice'] as $precio) {
					if ( $precio['reduction'] == 0 ) {
						$value['Productotienda']['valor_final'] = $value['Productotienda']['valor_iva'];

					}else{

						$value['Productotienda']['valor_final'] = $this->precio($value['Productotienda']['valor_iva'], ($precio['reduction'] * 100 * -1) );
						$value['Productotienda']['descuento'] = ($precio['reduction'] * 100 * -1 );

					}
				}

				$google[$key]['g:id']           = $value['Productotienda']['id_product'];
				$google[$key]['g:title']        = $value['pl']['name'];
				$google[$key]['g:description']  = strip_tags($value['pl']['description_short']) . '';
				$google[$key]['g:link']         = sprintf('%s%s-%s.html', $sitioUrl, $value['pl']['link_rewrite'], $value['Productotienda']['id_product']);
				$google[$key]["g:image_link"]   = $value[0]['url_image_large'];
				$google[$key]['g:availability'] = ($value['Productotienda']['quantity'] > 0) ? 'in stock' : 'out of stock';
				$google[$key]['g:price']        = $value['Productotienda']['valor_final'];
				$google[$key]['g:product_type'] = $this->tree($cate);
				$google[$key]['g:brand']        = (empty($value['Productotienda']['id_manufacturer'])) ? 'No especificado' : $value['Marca']['name'] ;
				$google[$key]['g:mpn']         = $value['Productotienda']['reference'];
				$google[$key]['g:condition']    = $value['Productotienda']['condition'];
				$google[$key]['g:adult']        = 'no';
				$google[$key]['g:age_group']    = 'adult';


				# Se agrega la info del producto al Feed
				$item = GoogleShopping::createItem();
				$item->id($google[$key]['g:id']);
				$item->title($google[$key]['g:title']);
				$item->description($google[$key]['g:description']);
				$item->price($google[$key]['g:price']);
				$item->link($google[$key]['g:link']);
				$item->image_link($google[$key]['g:image_link']);
				$item->availability($google[$key]['g:availability']);
				$item->product_type($google[$key]['g:product_type']);
				$item->brand($google[$key]['g:brand']);
				$item->mpn($google[$key]['g:mpn']);
				$item->condition($google[$key]['g:condition']);
				$item->adult($google[$key]['g:adult']);
				$item->age_group($google[$key]['g:age_group']);
				
			}

			$out = $google;
		}

		GoogleShopping::asRss(true);
		#$salida = GoogleShopping::asRss();
		
		
		#file_put_contents('google_feed2.xml', $salida);
		
		exit;
		/*if ($this->request->params['formato'] == 'xml') {
			
			App::uses('Xml', 'Utility');
			
			$xml2   = Xml::Build($xmlArray);
			$salida = $xml2->asXML();

			$this->layout = 'empty';
		}*/
		
		
		

		#file_put_contents('google_feed.xml', $salida);
		#echo $salida;
	

		$this->set(compact('salida', 'xmlArray', 'out'));
    }



    public function admin_index() 
    {
    	$paginate = array(); 
    	$conditions = array();
    	$total = 0;
    	$totalMostrados = 0;
    	$categorias = array();

    	$textoBuscar = null;

		// Filtrado de productos por formulario
		if ( $this->request->is('post') ) {

			if ( ! empty($this->request->data['Filtro']['findby']) && empty($this->request->data['Filtro']['nombre_buscar']) ) {
				$this->Session->setFlash('Ingrese nombre o referencia del producto' , null, array(), 'danger');
				$this->redirect(array('action' => 'index'));
			}

			if ( ! empty($this->request->data['Filtro']['findby']) && ! empty($this->request->data['Filtro']['nombre_buscar']) ) {
				$this->redirect(array('controller' => 'productotiendas', 'action' => 'index', 'findby' => $this->request->data['Filtro']['findby'], 'nombre_buscar' => $this->request->data['Filtro']['nombre_buscar']));
			}
		}

		//Buscamos el prefijo de la tienda
		$tienda = ClassRegistry::init('Tienda')->find('first', array(
		'conditions' => array(
			'Tienda.id' => $this->Session->read('Tienda.id')
			)
		));

		// Virificar existencia de la tienda
		if (empty($tienda)) {
			$this->Session->setFlash('La tienda seleccionada no existe' , null, array(), 'danger');
			$this->redirect('/');
		}

		// Verificar que la tienda esté configurada
		if (empty($tienda['Tienda']['prefijo']) || empty($tienda['Tienda']['prefijo']) || empty($tienda['Tienda']['configuracion'])) {
			$this->Session->setFlash('La tienda no está configurada completamente. Verifiquela y vuelva a intentarlo' , null, array(), 'danger');
			$this->redirect('/');
		}

		// Opciones de paginación
		$paginate = array_replace_recursive(array(
			'limit' => 10,
			'fields' => array(
				'concat(\'http://' . $tienda['Tienda']['url'] . '/img/p/\',mid(im.id_image,1,1),\'/\', if (length(im.id_image)>1,concat(mid(im.id_image,2,1),\'/\'),\'\'),if (length(im.id_image)>2,concat(mid(im.id_image,3,1),\'/\'),\'\'),if (length(im.id_image)>3,concat(mid(im.id_image,4,1),\'/\'),\'\'),if (length(im.id_image)>4,concat(mid(im.id_image,5,1),\'/\'),\'\'), im.id_image, \'.jpg\' ) AS url_image',
				'Productotienda.id_product', 
				'pl.name', 
				'Productotienda.price', 
				'pl.link_rewrite', 
				'Productotienda.reference', 
				'Productotienda.show_price'
			),
			'joins' => array(
				array(
		            'table' => sprintf('%sproduct_lang', $tienda['Tienda']['prefijo']),
		            'alias' => 'pl',
		            'type'  => 'LEFT',
		            'conditions' => array(
		                'Productotienda.id_product=pl.id_product'
		            )

	        	),
	        	array(
		            'table' => sprintf('%simage', $tienda['Tienda']['prefijo']),
		            'alias' => 'im',
		            'type'  => 'LEFT',
		            'conditions' => array(
		                'Productotienda.id_product = im.id_product',
		                'im.cover' => 1
		            )
	        	)
			),
			'contain' => array(
				'Categoria'
			),
			'conditions' => array(
				'Productotienda.active' => 1,
				'Productotienda.available_for_order' => 1,
				'Productotienda.id_shop_default' => 1,
				'pl.id_lang' => 1
			)
		));

		/*******************************************
		 * 
		 * Aplicar a todos los modelos dinámicos
		 * 
		 ******************************************/
		$this->cambiarConfigDB($tienda['Tienda']['configuracion']);

		/**
		* Buscar por
		*/
		if ( !empty($this->request->params['named']['findby']) && !empty($this->request->params['named']['nombre_buscar']) ) {

			/**
			* Agregar condiciones a la paginación
			* según el criterio de busqueda (código de referencia o nombre del producto)
			*/
			switch ($this->request->params['named']['findby']) {
				case 'code':
					$paginate		= array_replace_recursive($paginate, array(
						'conditions'	=> array(
							'Productotienda.reference' => trim($this->request->params['named']['nombre_buscar'])
						)
					));
					break;
				
				case 'name':
					$paginate		= array_replace_recursive($paginate, array(
						'conditions'	=> array(
							'pl.name LIKE "%' . trim($this->request->params['named']['nombre_buscar']) . '%"'
						)
					));
					break;
			}
			// Texto ingresado en el campo buscar
			$textoBuscar = $this->request->params['named']['nombre_buscar'];
			
		}else if ( ! empty($this->request->params['named']['findby'])) {
			$this->Session->setFlash('No se aceptan campos vacios.' ,  null, array(), 'danger');
		}

		// Total de registros de la tienda
		$total 		= $this->Productotienda->find('count', array(
			'joins' => array(
				array(
		            'table' => sprintf('%sproduct_lang', $tienda['Tienda']['prefijo']),
		            'alias' => 'pl',
		            'type'  => 'LEFT',
		            'conditions' => array(
		                'Productotienda.id_product=pl.id_product'
		            )

	        	),
	        	array(
		            'table' => sprintf('%simage', $tienda['Tienda']['prefijo']),
		            'alias' => 'im',
		            'type'  => 'LEFT',
		            'conditions' => array(
		                'Productotienda.id_product = im.id_product',
		                'im.cover' => 1
		            )
	        	)
			),
			'conditions' => array(
				'Productotienda.active' => 1,
				'Productotienda.available_for_order' => 1,
				'Productotienda.id_shop_default' => 1,
				'pl.id_lang' => 1
			)
		));

		$categorias = $this->Productotienda->Categoria->find('list', array('conditons' => array('Categoria.activo' => 1)));

		$this->paginate = $paginate;

		$productos	= $this->paginate();
		$totalMostrados = count($productos);

		if (empty($productos)) {
			$this->Session->setFlash(sprintf('No se encontraron resultados para %s', $this->request->params['named']['nombre_buscar']) , null, array(), 'danger');
			$this->redirect(array('action' => 'index'));
		}

		BreadcrumbComponent::add('Productos Tiendas');

		$this->set(compact('productos', 'total', 'totalMostrados', 'textoBuscar', 'categorias', 'tienda'));

    }


    public function admin_associate($id = null, $tiendaId = null) {

    	if (is_null($tiendaId) || empty($tiendaId)) {
    		$this->Session->setFlash('No se ubicó la tienda del producto', null, array(), 'danger');
			$this->redirect(array('action' => 'index'));
    	}

    	//Buscamos el prefijo de la tienda
		$tienda = ClassRegistry::init('Tienda')->find('first', array(
		'conditions' => array(
			'Tienda.id' => $tiendaId
			)
		));

		// Virificar existencia de la tienda
		if (empty($tienda)) {
			$this->Session->setFlash('La tienda seleccionada no existe' , null, array(), 'danger');
			$this->redirect(array('action' => 'index'));
		}

		// Verificar que la tienda esté configurada
		if (empty($tienda['Tienda']['prefijo']) || empty($tienda['Tienda']['prefijo']) || empty($tienda['Tienda']['configuracion'])) {
			$this->Session->setFlash('La tienda no está configurada completamente. Verifiquela y vuelva a intentarlo' , null, array(), 'danger');
			$this->redirect(array('action' => 'index'));
		}


		/*******************************************
		 * 
		 * Aplicar a todos los modelos dinámicos
		 * 
		 ******************************************/
		$this->cambiarConfigDB($tienda['Tienda']['configuracion']);

    	if ( ! $this->Productotienda->exists($id) ) {
    		$this->Session->setFlash('No se encontraron el producto seleccionado', null, array(), 'danger');
			$this->redirect(array('action' => 'index'));
    	}


    	if ($this->request->is('post')) {

    		$this->Productotienda->CategoriasProductotienda->deleteAll(
    			array(
					'CategoriasProductotienda.id_product' => $this->request->data['Productotienda']['id_product']
				)
    		);

    		if ( $this->Productotienda->save($this->request->data) )
    		{

				$this->Session->setFlash('Registro editado correctamente', null, array(), 'success');
				$this->redirect(array('action' => 'index'));

			}
			else
			{
				$this->Session->setFlash('Error al guardar el registro. Por favor intenta nuevamente.', null, array(), 'danger');
			}
    	}

    	// Opciones de paginación
		$producto = $this->Productotienda->find('first', array(
			'fields' => array(
				'concat(\'http://' . $tienda['Tienda']['url'] . '/img/p/\',mid(im.id_image,1,1),\'/\', if (length(im.id_image)>1,concat(mid(im.id_image,2,1),\'/\'),\'\'),if (length(im.id_image)>2,concat(mid(im.id_image,3,1),\'/\'),\'\'),if (length(im.id_image)>3,concat(mid(im.id_image,4,1),\'/\'),\'\'),if (length(im.id_image)>4,concat(mid(im.id_image,5,1),\'/\'),\'\'), im.id_image, \'-large_default.jpg\' ) AS url_image',
				'Productotienda.id_product', 
				'pl.name', 
				'Productotienda.price', 
				'pl.link_rewrite', 
				'Productotienda.reference', 
				'Productotienda.show_price'
			),
			'joins' => array(
				array(
		            'table' => sprintf('%sproduct_lang', $tienda['Tienda']['prefijo']),
		            'alias' => 'pl',
		            'type'  => 'LEFT',
		            'conditions' => array(
		                'Productotienda.id_product=pl.id_product'
		            )

	        	),
	        	array(
		            'table' => sprintf('%simage', $tienda['Tienda']['prefijo']),
		            'alias' => 'im',
		            'type'  => 'LEFT',
		            'conditions' => array(
		                'Productotienda.id_product = im.id_product',
		                'im.cover' => 1
		            )
	        	)
			),
			'contain' => array(
				'Categoria' => array(
					'conditions' => array(
						'Categoria.activo' => 1,
						'Categoria.tienda_id' => $tiendaId
					)
				),
				'TaxRulesGroup' => array(
					'TaxRule' => array(
						'Tax'
					)
				),
				'SpecificPrice' => array(
					'conditions' => array(
						'OR' => array(
							array(
								'SpecificPrice.from <= "' . date('Y-m-d H:i:s') . '"',
								'SpecificPrice.to >= "' . date('Y-m-d H:i:s') . '"'
							),
							array(
								'SpecificPrice.from' => '0000-00-00 00:00:00',
								'SpecificPrice.to >= "' . date('Y-m-d H:i:s') . '"'
							),
							array(
								'SpecificPrice.from' => '0000-00-00 00:00:00',
								'SpecificPrice.to' => '0000-00-00 00:00:00'
							),
							array(
								'SpecificPrice.from <= "' . date('Y-m-d H:i:s') . '"',
								'SpecificPrice.to' => '0000-00-00 00:00:00'
							)
						)
					)
				),
				'SpecificPricePriority'
			),
			'conditions' => array(
				'Productotienda.id_product' => $id,
				'Productotienda.active' => 1,
				'Productotienda.available_for_order' => 1,
				'Productotienda.id_shop_default' => 1,
				'pl.id_lang' => 1
			)
		));
		
		// Retornar valor con iva;
		if ( !isset($producto['TaxRulesGroup']['TaxRule'][0]['Tax']['rate']) ) {
			$producto['Productotienda']['valor_iva'] = $producto['Productotienda']['price'];	
		}else{
			$producto['Productotienda']['valor_iva'] = $this->precio($producto['Productotienda']['price'], $producto['TaxRulesGroup']['TaxRule'][0]['Tax']['rate']);
		}
		
		// Criterio del precio específico
		foreach ($producto['SpecificPricePriority'] as $criterio) {
			$precioEspecificoPrioridad = explode(';', $criterio['priority']);
		}

		$producto['Productotienda']['valor_final'] = $producto['Productotienda']['valor_iva'];

		// Retornar precio espeficico según criterio
		foreach ($producto['SpecificPrice'] as $precio) {

			if ( $precio['reduction'] == 0 ) {
				$producto['Productotienda']['valor_final'] = $producto['Productotienda']['valor_iva'];
			}else{
				$producto['Productotienda']['valor_final'] = $this->precio($producto['Productotienda']['valor_iva'], ($precio['reduction'] * 100 * -1) );
				$producto['Productotienda']['descuento'] = ($precio['reduction'] * 100 * -1 );

			}
		}
		
		$categorias = $this->Productotienda->Categoria->find('list', array('conditions' => array('Categoria.activo' => 1, 'Categoria.tienda_id' => $tiendaId)));

		BreadcrumbComponent::add('Productos Tiendas', '/productotiendas');
		BreadcrumbComponent::add('Asociar ');

		$this->set(compact('producto', 'categorias', 'tienda'));

    }


    public function admin_obtener_productos( $tienda = '', $palabra = '') {
    	if (empty($tienda) || empty($palabra)) {
    		echo json_encode(array('0' => array('value' => '', 'label' => 'Ingrese referencia')));
    		exit;
    	}
   		
   		/*******************************************
		 * 
		 * Aplicar a todos los modelos dinámicos
		 * 
		 ******************************************/
   		$this->cambiarConfigDB($this->tiendaConf($tienda));

   		$productos = $this->Productotienda->find('all', array(
   			'conditions' => array(
   				'Productotienda.reference LIKE' => $palabra . '%'
   			),
   			'contain' => array(
   				'Lang',
   				'TaxRulesGroup' => array(
					'TaxRule' => array(
						'Tax'
					)
				),
				'SpecificPrice' => array(
					'conditions' => array(
						'OR' => array(
							array(
								'SpecificPrice.from <= "' . date('Y-m-d H:i:s') . '"',
								'SpecificPrice.to >= "' . date('Y-m-d H:i:s') . '"'
							),
							array(
								'SpecificPrice.from' => '0000-00-00 00:00:00',
								'SpecificPrice.to >= "' . date('Y-m-d H:i:s') . '"'
							),
							array(
								'SpecificPrice.from' => '0000-00-00 00:00:00',
								'SpecificPrice.to' => '0000-00-00 00:00:00'
							),
							array(
								'SpecificPrice.from <= "' . date('Y-m-d H:i:s') . '"',
								'SpecificPrice.to' => '0000-00-00 00:00:00'
							)
						)
					)
				)
			),
			'limit' => 3)
   		);

   		if (empty($productos)) {
    		echo json_encode(array('0' => array('id' => '', 'value' => 'No se encontraron coincidencias')));
    		exit;
    	}
    	
    	foreach ($productos as $index => $producto) {
    		$arrayProductos[$index]['id'] = $producto['Productotienda']['id_product'];
			$arrayProductos[$index]['value'] = sprintf('%s - %s', $producto['Productotienda']['reference'], $producto['Lang'][0]['ProductotiendaIdioma']['name']);

			$tabla = '<tr>';
	    	$tabla .= '<td><input type="hidden" name="data[Productotienda][[*ID*]][id_product]" value="[*ID*]" class="js-input-id_product">[*ID*]</td>';
	    	$tabla .= '<td>[*REFERENCIA*]</td>';
	    	$tabla .= '<td>[*NOMBRE*]</td>';
	    	$tabla .= '<td><label class="label label-form label-success">[*PRECIO*]</label></td>';
	    	$tabla .= '<td><input type="number" name="data[Productotienda][[*ID*]][cantidad]" class="form-control js-number" min="0" max="100000" placeholder="X" style="max-width: 70px;" value="1" /></td>';
	    	$tabla .= '<td><button class="quitar btn btn-danger">Quitar</button></td>';
	    	$tabla .= '</tr>';

			// Armamos la tabla
			$tabla = str_replace('[*ID*]', $producto['Productotienda']['id_product'] , $tabla);
			$tabla = str_replace('[*REFERENCIA*]', $producto['Productotienda']['reference'] , $tabla);
			$tabla = str_replace('[*NOMBRE*]', $producto['Lang'][0]['ProductotiendaIdioma']['name'] , $tabla);

			$precio_normal 		= $this->precio($producto['Productotienda']['price'], $producto['TaxRulesGroup']['TaxRule'][0]['Tax']['rate']);
			
			if ( ! empty($producto['SpecificPrice']) ) {
				if ($producto['SpecificPrice'][0]['reduction'] == 0) {
					$tabla = str_replace('[*PRECIO*]', CakeNumber::currency($precio_normal , 'CLP'), $tabla);
				}else {
					$precio_descuento	= $this->precio($precio_normal, ($producto['SpecificPrice'][0]['reduction'] * 100 * -1) );
					$tabla = str_replace('[*PRECIO*]', CakeNumber::currency($precio_descuento , 'CLP') , $tabla);
				}
			}else{
				$tabla = str_replace('[*PRECIO*]', CakeNumber::currency($precio_normal , 'CLP') , $tabla);
			}
			$arrayProductos[$index]['todo'] = $tabla;
    	}

    	echo json_encode($arrayProductos, JSON_FORCE_OBJECT);
    	exit;
    }
    
}