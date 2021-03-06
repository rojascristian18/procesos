<?php
App::uses('AppModel', 'Model');
class ProductotiendaProspecto extends AppModel
{
	/**
	 * CONFIGURACION DB
	 */
	
	/**
	 * Set Cake config DB
	 */
	public $name = 'ProductotiendaProspecto';
	public $useTable = 'productotiendas_prospectos';
	public $primaryKey = 'id';

	/**
	 * Use Toolmania Connect
	 */
	public $useDbConfig = 'reportes';

	/**
	 * BEHAVIORS
	 */
	var $actsAs			= array(
		/**
		 * IMAGE UPLOAD
		 */
		/*
		'Image'		=> array(
			'fields'	=> array(
				'imagen'	=> array(
					'versions'	=> array(
						array(
							'prefix'	=> 'mini',
							'width'		=> 100,
							'height'	=> 100,
							'crop'		=> true
						)
					)
				)
			)
		)
		*/
	);

	/**
	 * VALIDACIONES
	 */
}