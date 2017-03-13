$.extend({
	app: {
		seleccionDireccion: {
			init: function() {
				if ($('.js-direccion-utilizar').length) {
					$.app.seleccionDireccion.bind();
				}
			},
			bind: function() {
	
				$('input[type="submit"]').on('click', function(event){
					if ($('.js-direccion-utilizar:checked').length > 1 ) {
						event.preventDefault();
						noty({text: 'Seleccione solo una dirección para cotizar', layout: 'topRight', type: 'error'});
					}
				});
				
			}
		},
		validarProspecto: {
			init: function(){
				if ($('#ProspectoAdminAddForm').length) {
					$.app.validarProspecto.bind();
				}
			},
			bind: function(){
				var validator = $("#ProspectoAdminAddForm").validate({
                    rules: {
                        'data[Prospecto][nombre]': {
                            required: true
                        },
                        'data[Prospecto][descripcion]': {
                            required: true
                        }
                    },
                    messages: {
                    	'data[Prospecto][nombre]': {
                            required: 'Ingrese un nombre al prospecto'
                        },
                        'data[Prospecto][descripcion]': {
                            required: 'Ingrese una descripción al prospecto'
                        },
                        'data[Cliente][][email]': {
                        	email: 'Ingrese un email válido'
                        },
                        'data[Cliente][][siret]': {
                        	required: 'Ingrese el rut de la empresa'
                        },
                        'input[type="tel"]': {
                        	minlength : "Ingrese un teléfono de 9 dígitos",
                        	maxlength : "Ingrese un teléfono de 9 dígitos"
                        }
                    }
                });

                // Semaforo de rut empresa
                $('.js-tipo-cliente').on('change', function(){
                	$this 		= $(this),
                	campoRut 	= $this.parents('table').eq(0).find('.js-rut-empresa');

                	if ($this.val() == 2) {
                		campoRut.attr('required', 'required');
                	}else{
                		campoRut.removeAttr('required');
                	}

                });
			}	
		},
		rutChileno: {
			init: function() {
				if ($('.rut').length) {
					$.app.rutChileno.bind()
				}
			},
			bind: function(){		

				$('.rut').rut();
				$(document).on('rutInvalido', '.rut', function(e) {
					if ( $(this).val() != '' ) {
						if ($(this).hasClass('valid')) {
					    	$(this).removeClass('valid');
					    }
					    $(this).addClass('error');
					    if ( $(this).parent().find('label').length == 0 ) {
					    	$(this).parent().append('<label id="' + $(this).attr('id') + '-error" class="rut-error" for="' + $(this).attr('id') + '">Ingrese un rut válido</label>');
					    }
					}else{
						$(this).parent().find('label').remove();
						$(this).removeClass('valid');
						$(this).removeClass('error');
					}
				});

				$(document).on('rutValido', '.rut', function(e, rut, dv) {
				    if ($(this).hasClass('error')) {
				    	$(this).removeClass('error');
				    }
				    if ( $(this).parent().find('label').length > 0 ) {
				    	$(this).parent().find('label').remove();
				    }

				    $(this).addClass('valid');	
					
				});
			}
		},
		mascara: {
			init: function(){
				if ($('input[class^="mascara_"]').length) {
					$.app.mascara.bind();
				}
			},
			bind: function(){
				$('.mascara_fono').mask('99999-9999', {clearIfNotMatch: true, placeholder: "xxxxx-xxxx"});
			}
		},
		toggle: {
			init: function() {
				if ($('.btn-toggle').length) {
					$.app.toggle.bind();
				}
			},
			bind: function() {
				$(document).on('click', '.btn-toggle', function(){
					$(this).find('.fa').toggle();
				});
			}
		},
		loader: {
			mostrar: function(){
				$('.loader').css('display', 'block');
			},
			ocultar: function(){
				$('.loader').css('display', 'none');
			}
		},
		obtenerRegiones: function(tienda, pais, contexto, region){
			console.info('Obtener región ejecutada');
			$.get( webroot + 'regiones/regiones_por_tienda_pais/' + tienda + '/' + pais, function(respuesta){
				contexto.find('.js-region').html(respuesta);
				if (region > 0) {
					contexto.find('.js-region').val(region);
				}
				
				$.app.loader.ocultar();
			})
			.fail(function(){
				$.app.loader.ocultar();

				noty({text: 'Ocurrió un error al obtener la información. Intente nuevamente.', layout: 'topRight', type: 'error'});

				setTimeout(function(){
					$.noty.closeAll();
				}, 10000);
			});
		},
		resetearTablas: function(){

			$(document).find('.js-clon-clonada').remove();

			// Clonar tabla
			$('.js-clon-contenedor').each(function(){

				var $this 			= $(this),
					tablaInicial 	= $this.find('.js-clon-base'),
					tablaClonada 	= tablaInicial.clone();

				tablaClonada.removeClass('js-clon-base');
				tablaClonada.removeClass('hidden');
				tablaClonada.addClass('js-clon-clonada');
				tablaClonada.find('input, select, textarea').removeAttr('disabled');

				$('#accordion').append(tablaClonada);

				$.app.clonarTabla.reindexar();
			});
		},
		obtenerPaises: {
			init: function(){
				if ($('.js-pais').length) {
					var tienda = $('.js-tienda').val();
					if (typeof(tienda) != 'undefined') {
						$.app.obtenerPaises.bind(tienda);
					}
				}
			},
			bind: function(tienda) {
				$.get( webroot + 'paises/paises_por_tienda/' + tienda, function(respuesta){
					$('.js-pais').html(respuesta);
					$.app.loader.ocultar();
				})
				.fail(function(){
					$.app.loader.ocultar();

					noty({text: 'Ocurrió un error al obtener la información. Intente nuevamente.', layout: 'topRight', type: 'error'});

					setTimeout(function(){
						$.noty.closeAll();
					}, 10000);
				});

				$(document).on('change', '.js-pais', function(e, data) {		
					$.app.loader.mostrar();
					var pais 		= $(this).val(),
						contexto 	= $(this).parents('table').eq(0),
						region 		= 0;
					console.log(data)
					//Sí data es un objeto se carga el pais y la región 
					if(typeof(data) == 'object') {
						console.info('Ejecutado desde nuevo campo pais');
						$.app.obtenerRegiones(tienda, data.valorPais, contexto, data.valorRegion);
					}else{
						console.info('Ejecutado desde campo pais existente');
						$.app.obtenerRegiones(tienda, pais, contexto, region);
					}
				});
			}
		},
		autocompletarBuscar: {
			init: function() {
				if ( $('.input-clientes-buscar').length > 0 ) {
					$.app.autocompletarBuscar.clientesBuscar();
				}
			},	
			obtenerDatosCliente: function( tienda, idCliente){
				/**
				 * Reseteamos las tablas de direcciones
				 */
				$.app.resetearTablas();

				$.get( webroot + 'clientes/cliente_por_tienda/' + tienda + '/' + idCliente, function(respuesta){
					var cliente 	= $.parseJSON(respuesta),	
						direcciones = cliente.Clientedireccion;

					$('#Cliente1Ape').val(cliente.Cliente.ape);
					$('#Cliente1Siret').val(cliente.Cliente.siret);
					$('#Cliente1IdGender').val(cliente.Cliente.id_gender);
					$('#Cliente1Firstname').val(cliente.Cliente.firstname);
					$('#Cliente1Lastname').val(cliente.Cliente.lastname);
					
					if (typeof(direcciones) == 'object') {
						console.info('Tiene ' + direcciones.length + ' dirección/es');
						for (var itr = 0; itr <= direcciones.length - 1; itr++) {
							if (direcciones.length > 1 && itr > 0) {
								console.info('Se crea tabla de direcciones nueva');
								$('.js-clon-agregar').trigger('click');
							}

							console.info('Dirección ' + (itr + 1) + ' Id: ' + direcciones[itr].id_address );

							// Se completan los campos de direcciones (itr + 1) para seleccionar el campo que no está oculto
							$('.js-direccion-id').eq(itr + 1).val(direcciones[itr].id_address);
							$('.js-direccion-alias').eq(itr + 1).val(direcciones[itr].alias);
							$('.js-direccion-empresa').eq(itr + 1).val(direcciones[itr].company);
							$('.js-direccion-empresa-rut').eq(itr + 1).val(direcciones[itr].vat_number);
							$('.js-direccion-nombre').eq(itr + 1).val(direcciones[itr].firstname);
							$('.js-direccion-apellido').eq(itr + 1).val(direcciones[itr].lastname);
							$('.js-direccion-direccion1').eq(itr + 1).val(direcciones[itr].address1);
							$('.js-direccion-direccion2').eq(itr + 1).val(direcciones[itr].address2);
							$('.js-direccion-ciudad').eq(itr + 1).val(direcciones[itr].city);
							
							$('.js-direccion-pais').eq(itr + 1).val(direcciones[itr].id_country);

							// Pasamos el id de pais y el id de región al enevto change para que se seleccionen automáticamente
							$('.js-direccion-pais').trigger('change', [{valorPais : direcciones[itr].id_country, valorRegion : direcciones[itr].id_state}] );
							
							$('.js-direccion-region').eq(itr + 1).val(direcciones[itr].id_state);
							$('.js-direccion-fono').eq(itr + 1).val(direcciones[itr].phone);
							$('.js-direccion-celular').eq(itr + 1).val(direcciones[itr].phone_mobile);
							$('.js-direccion-otro').eq(itr + 1).val(direcciones[itr].other);
						}
					}

					$.app.loader.ocultar();

					noty({text: 'Se completaron todos los campos del cliente.', layout: 'topRight', type: 'success'});

					setTimeout(function(){
						$.noty.closeAll();
					}, 10000);
		     	})
		     	.fail(function(){
		     		$.app.loader.ocultar();

					noty({text: 'Ocurrió un error al obtener el cliente. Intente nuevamente.', layout: 'topRight', type: 'error'});

					setTimeout(function(){
						$.noty.closeAll();
					}, 10000);
		     	});

			},
			clientesBuscar: function(){

				$('#ProspectoExistente').on('change', function(){
					if ( !$(this).is(':checked')) {
						$('#ClienteEmail').val('');
						$('#ClienteIdGender').val('');
						$('#ClienteFirstname').val('');
						$('#ClienteLastname').val('');
						$('#ClienteBirthday').val('');
						$('input, select, texarea').removeAttr('disabled');
					}
				});

				$('.input-clientes-buscar').each(function(){
					var $esto 	= $(this),
						tienda 	= $('.js-tienda').val();
					
					if (typeof(tienda) == 'undefined') {
						alert('Seleccione una tienda');
					}

					$esto.autocomplete({
					   	source: function(request, response) {
					      	$.get( webroot + 'clientes/clientes_por_tienda/' + tienda + '/' + request.term, function(respuesta){
								if ($('#ProspectoExistente:checked').length > 0) {
									response( $.parseJSON(respuesta) );
								}else{
									
								}
					      	})
					      	.fail(function(){
								$.app.loader.ocultar();

								noty({text: 'Ocurrió un error al obtener la información. Intente nuevamente.', layout: 'topRight', type: 'error'});

								setTimeout(function(){
									$.noty.closeAll();
								}, 10000);
							});
					    },
					    select: function( event, ui ) {
					        console.log("Seleccionado: " + ui.item.value + " id " + ui.item.id);
					        $.app.loader.mostrar();
					        $.app.autocompletarBuscar.obtenerDatosCliente(tienda, ui.item.id);

					    },
					    open: function(event, ui) {
		                    var autocomplete = $(".ui-autocomplete:visible");
		                    var oldTop = autocomplete.offset().top;
		                    var width  = $esto.width();
		                    var newTop = oldTop - $esto.height() + 25;

		                    autocomplete.css("top", newTop);
		                    autocomplete.css("width", width);
		                    autocomplete.css("position", 'absolute');
		                }
					});
				});
			}
		},
		cambioTienda: {
			init: function() {
				if ($('.js-tienda').length) {
					$.app.cambioTienda.bind();
				}
			},
			bind: function() {
				$('.js-tienda').on('change', function(){
					$.app.loader.mostrar();
					$.app.obtenerPaises.init();
				});
			}
		},
		clonarTabla: {
			clonar: function() {
				// Clonar tabla
				$('.js-clon-contenedor').each(function(){

					var $this 			= $(this),
						tablaInicial 	= $this.find('.js-clon-base'),
						tablaClonada 	= tablaInicial.clone();
						console.log('Contenedor clonar disparado');
					tablaClonada.removeClass('js-clon-base');
					tablaClonada.removeClass('hidden');
					tablaClonada.addClass('js-clon-clonada');
					tablaClonada.find('input, select, textarea').removeAttr('disabled');
					
					$this.append(tablaClonada);

					$.app.clonarTabla.reindexar();
				});
			},
			init: function(){
				if($('.js-clon-contenedor').length) {
					$.app.clonarTabla.clonar();
					$.app.clonarTabla.bind();
				}
			},
			bind: function(){

				// Agregar tabla click
				$('.js-clon-agregar').on('click', function(event){
					event.preventDefault();
					$.app.clonarTabla.clonar();
				});

			},
			reindexar: function() {
				var $contenedor			= $('.js-clon-contenedor');

				$contenedor.find('.panel').each(function(indice){
					
					$(this).find('.panel-heading').each(function(){
						
						var $headPanel 				= $(this),
							linkHead 				= $headPanel.find('a[data-toggle="collapse"]');
						
						$headPanel.attr('id', 'PanelHeading' + indice);
						linkHead.attr('href', '#PanelCollapse' + indice);

						if ( $headPanel.parent('.panel').find('.collapse').hasClass('in') ) {
							linkHead.html('<i class="fa fa-chevron-down" style="display: none;" aria-hidden="true"></i><i class="fa fa-chevron-up" aria-hidden="true"></i> <b>Dirección ' + indice + '</b>');
						}else{
							linkHead.html('<i class="fa fa-chevron-down" aria-hidden="true"></i><i class="fa fa-chevron-up" style="display: none;" aria-hidden="true"></i> <b>Dirección ' + indice + '</b>');
						}

						$headPanel.find('input').each(function()
						{
							var $that		= $(this),
								nombre		= $that.attr('name').replace(/[(\d)]/g, (indice));

							$that.attr('name', nombre);
						});
						
					});

					$(this).find('.panel-collapse').each(function(){
						var $collapsePanel 		= $(this);
						
						$collapsePanel.attr('id', 'PanelCollapse' + indice);
					});
				});

				$contenedor.find('.table').each(function(index)
				{	
					$(this).find('input, select, textarea').each(function()
					{
						var $that		= $(this),
							nombre		= $that.attr('name').replace(/[(\d)]/g, (index));

						$that.attr('name', nombre);
					});

					$(this).find('label').each(function()
					{
						var $that		= $(this),
							nombre		= $that.attr('for').replace(/[(\d)]/g, (index));

						$that.attr('for', nombre);
					});
				});

				$.app.mascara.init();
				
				
			}
		},
		init: function(){
			$.app.clonarTabla.init();
			$.app.toggle.init();
			$.app.cambioTienda.init();
			$.app.obtenerPaises.init();
			$.app.autocompletarBuscar.init();
			$.app.mascara.init();
			
			$.app.validarProspecto.init();
			$.app.seleccionDireccion.init();
		}
	}
});

$(document).ready(function(){
	$.app.init();
});