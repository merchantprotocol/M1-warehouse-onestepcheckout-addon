/**
 * Mage Plugins, Inc
 *
 * NOTICE OF LICENSE
 * 
 * This source file is subject to the InnoExts Commercial License
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * https://mageplugins.net/commercial-license-agreement
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to mageplugins@gmail.com so we can send you a copy immediately.
 * 
 * @category    MP
 * @package     MP_WarehouseOneStepCheckout
 * @copyright   Copyright (c) 2006-2018 Mage Plugins, Inc. and affiliates (https://mageplugins.net/)
 * @license     https://mageplugins.net/commercial-license-agreement  InnoExts Commercial License
 */

function get_shipping_methods(el) {
    if($(el).type && $(el).type.toLowerCase() == 'radio') {
        var el = $(el).form;
    }
    var shippingMethods = {};
    $(el).select('.warehouse').each(function (warehouseElement) {
        var warehouse = $(warehouseElement);
        var stockId = warehouse.readAttribute('warehouse:stockid');
        warehouse.select('.shipping-method').each(function (shippingMethodElement) {
            var shippingMethod = $(shippingMethodElement);
            if (shippingMethodElement.checked) {
                shippingMethods['shipping_method[' + stockId + ']'] = shippingMethod.getValue();
            }
        });
    });
    return shippingMethods;
}

function get_separate_save_methods_function2(url, update_payments)
{
    if(typeof update_payments == 'undefined') {
        var update_payments = false;
    }

    return function(e)    {
        if(typeof e != 'undefined')    {
            var element = e.element();

            if(!String(element.name).startsWith('shipping_method'))    {
                update_payments = false;
            }
        }

        var form = $('onestepcheckout-form');
        var shipping_method = get_shipping_methods(form);
        
        var payment_method = $RF(form, 'payment[method]');
        var totals = get_totals_element();

        var freeMethod = $('p_method_free');
        if(freeMethod){
            payment.reloadcallback = true;
            payment.countreload = 1;
        }

        totals.update('<div class="loading-ajax">&nbsp;</div>');

        if(update_payments)    {
            var payment_methods = $$('div.payment-methods')[0];
            payment_methods.update('<div class="loading-ajax">&nbsp;</div>');
        }

        var parameters = {
                payment_method: payment_method
        }
        
        for (parameter in shipping_method) {
            parameters[parameter] = shipping_method[parameter];
        }

        /* Find payment parameters and include */
        var items = $$('input[name^=payment]').concat($$('select[name^=payment]'));
        var names = items.pluck('name');
        var values = items.pluck('value');

        for(var x=0; x < names.length; x++)    {
            if(names[x] != 'payment[method]')    {
                parameters[names[x]] = values[x];
            }
        }

        new Ajax.Request(url, {
            method: 'post',
            onSuccess: function(transport)    {
            if(transport.status == 200)    {
                var data = transport.responseText.evalJSON();
                var form = $('onestepcheckout-form');

                totals.update(data.summary);

                if(update_payments)    {

                    payment_methods.replace(data.payment_method);

                    $$('div.payment-methods input[name^=payment\[method\]]').invoke('observe', 'click', get_separate_save_methods_function2(url));
                    $$('div.payment-methods input[name^=payment\[method\]]').invoke('observe', 'click', function() {
                        $$('div.onestepcheckout-payment-method-error').each(function(item) {
                            new Effect.Fade(item);
                        });
                    });

                    if($RF($('onestepcheckout-form'), 'payment[method]') != null)    {
                        try    {
                            var payment_method = $RF(form, 'payment[method]');
                            $('container_payment_method_' + payment_method).show();
                            $('payment_form_' + payment_method).show();
                        } catch(err)    {

                        }
                    }
                }
            }
        },
        parameters: parameters
        });
    }
}


function get_save_billing_function2(url, set_methods_url, update_payments, triggered)
{
    if(typeof update_payments == 'undefined')    {
        var update_payments = false;
    }

    if(typeof triggered == 'undefined')    {
        var triggered = true;
    }

    if(!triggered){
        return function(){return;};
    }

    return function()    {
        var form = $('onestepcheckout-form');
        var items = exclude_unchecked_checkboxes($$('input[name^=billing]').concat($$('select[name^=billing]')));
        var names = items.pluck('name');
        var values = items.pluck('value');
        var parameters = get_shipping_methods(form);


        var street_count = 0;
        for(var x=0; x < names.length; x++)    {
            if(names[x] != 'payment[method]')    {

                var current_name = names[x];

                if(names[x] == 'billing[street][]')    {
                    current_name = 'billing[street][' + street_count + ']';
                    street_count = street_count + 1;
                }

                parameters[current_name] = values[x];
            }
        }

        var use_for_shipping = $('billing:use_for_shipping_yes');




        if(use_for_shipping && use_for_shipping.getValue() != '1')    {
            var items = $$('input[name^=shipping]').concat($$('select[name^=shipping]'));
            var shipping_names = items.pluck('name');
            var shipping_values = items.pluck('value');
            var shipping_parameters = {};
            var street_count = 0;

            for(var x=0; x < shipping_names.length; x++)    {
                if(shipping_names[x] != 'shipping_method')    {
                    var current_name = shipping_names[x];
                    if(shipping_names[x] == 'shipping[street][]')    {
                        current_name = 'shipping[street][' + street_count + ']';
                        street_count = street_count + 1;
                    }

                    parameters[current_name] = shipping_values[x];
                }
            }
        }

        var shipment_methods = $$('div.onestepcheckout-shipping-method-block')[0];
        var shipment_methods_found = false;

        if(typeof shipment_methods != 'undefined') {
            shipment_methods_found = true;
        }

        if(shipment_methods_found)  {
            shipment_methods.update('<div class="loading-ajax">&nbsp;</div>');
        }

        var payment_method = $RF(form, 'payment[method]');
        parameters['payment_method'] = payment_method;
        parameters['payment[method]'] = payment_method;

        var payment_methods = $$('div.payment-methods')[0];
        payment_methods.update('<div class="loading-ajax">&nbsp;</div>');

        var totals = get_totals_element();
        totals.update('<div class="loading-ajax">&nbsp;</div>');


        new Ajax.Request(url, {
            method: 'post',
            onSuccess: function(transport)    {
            if(transport.status == 200)    {

                var data = transport.responseText.evalJSON();

                // Update shipment methods
                if(shipment_methods_found)  {
                    shipment_methods.update(data.shipping_method);
                }
                payment_methods.replace(data.payment_method);
                totals.update(data.summary);


            }
        },
        onComplete: function(transport){
            if(transport.status == 200)    {
                if(shipment_methods_found)  {
                    $$('dl.shipment-methods input').invoke('observe', 'click', get_separate_save_methods_function2(set_methods_url, update_payments));
                    $$('dl.shipment-methods input').invoke('observe', 'click', function() {
                        $$('div.onestepcheckout-shipment-method-error').each(function(item) {
                            new Effect.Fade(item);
                        });
                    });
                }

                $$('div.payment-methods input[name^=payment\[method\]]').invoke('observe', 'click', get_separate_save_methods_function2(set_methods_url));

                $$('div.payment-methods input[name^=payment\[method\]]').invoke('observe', 'click', function() {
                    $$('div.onestepcheckout-payment-method-error').each(function(item) {
                        new Effect.Fade(item);
                    });
                });

                if($RF(form, 'payment[method]') != null)    {
                    try    {
                        var payment_method = $RF(form, 'payment[method]');
                        $('container_payment_method_' + payment_method).show();
                        $('payment_form_' + payment_method).show();
                    } catch(err)    {

                    }
                }
            }
        },
        parameters: parameters
        });

    }
}


