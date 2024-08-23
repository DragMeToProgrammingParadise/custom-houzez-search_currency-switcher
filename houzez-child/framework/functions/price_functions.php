<?php

if( !function_exists('currency_maker')) {
    function currency_maker() {

        $price_maker_array = array();
        $multi_currency = houzez_option('multi_currency');
        $default_currency = houzez_option('default_multi_currency');
        if(empty($default_currency)) {
            $default_currency = 'USD';
        }
 
        if(isset($_GET['international'])) {
            $price_maker_array['currency'] = houzez_get_currency();
            $price_maker_array['decimals']  = intval(houzez_option( 'decimals' ));
            $price_maker_array['currency_position']  = houzez_option( 'currency_position' );
            $price_maker_array['thousands_separator']  = houzez_option( 'thousands_separator' );
            $price_maker_array['decimal_point_separator']  = houzez_option( 'decimal_point_separator' );
            return $price_maker_array;
        }

        if( $multi_currency == 1 ) {


            if(class_exists('Houzez_Currencies')) {
                $currencies = Houzez_Currencies::get_property_currency(get_the_ID());

                if($currencies) {

                    foreach ($currencies as $currency) {
                        $price_maker_array['currency'] = $currency->currency_symbol;
                        $price_maker_array['decimals']  = $currency->currency_decimal;
                        $price_maker_array['currency_position']  = $currency->currency_position;
                        $price_maker_array['thousands_separator']  = $currency->currency_thousand_separator;
                        $price_maker_array['decimal_point_separator']  = $currency->currency_decimal_separator;
                    }

                } else {

                        $currency = Houzez_Currencies::get_currency_by_code($default_currency);

                        $price_maker_array['currency'] = $currency['currency_symbol'];
                        $price_maker_array['decimals']  = $currency['currency_decimal'];
                        $price_maker_array['currency_position']  = $currency['currency_position'];
                        $price_maker_array['thousands_separator']  = $currency['currency_thousand_separator'];
                        $price_maker_array['decimal_point_separator']  = $currency['currency_decimal_separator'];
                }
            }

        } else {
            $price_maker_array['currency'] = houzez_get_currency();
            $price_maker_array['decimals']  = intval(houzez_option( 'decimals' ));
            $price_maker_array['currency_position']  = houzez_option( 'currency_position' );
            $price_maker_array['thousands_separator']  = houzez_option( 'thousands_separator' );
            $price_maker_array['decimal_point_separator']  = houzez_option( 'decimal_point_separator' );

        }
        return $price_maker_array;
    }
}


if( !function_exists('houzez_currency_switcher_filter') ) {
    function houzez_currency_switcher_filter($listing_price) {
        $current_currency = isset($_GET['international']) ? 'USD' : $_COOKIE[ "houzez_set_current_currency" ];
        if ( Fcc_currency_exists( $current_currency ) ) {    // validate current currency
            
            $base_currency = houzez_default_currency_for_switcher();
           
            $converted_price = Fcc_convert_currency( $listing_price, $base_currency, $current_currency );
            return Fcc_format_currency( $converted_price, $current_currency );
        }
        
    }
}
add_filter( 'houzez_currency_switcher_filter', 'houzez_currency_switcher_filter', 1, 9 );


if(!function_exists('houzez_get_currency')){
    function houzez_get_currency(){
        //get default currency from theme options
        $houzez_default_currency = fave_option( 'currency_symbol' );

        if(isset($_GET['international']) || empty($houzez_default_currency)){
            return esc_html__( '$' , 'houzez' );
        }
        return $houzez_default_currency;
    }
}


if ( ! function_exists( 'houzez_default_currency_for_switcher' ) ) {
    function houzez_default_currency_for_switcher() {
        $default_currency = houzez_option('houzez_base_currency');
        if ( !empty( $default_currency ) ) {
            return $default_currency;
        } else {
            $default_currency = 'USD';
        }
        return $default_currency;
    }
}



if( !function_exists('houzez_get_property_price') ) {
    function houzez_get_property_price ( $listing_price ) {

    
        if( $listing_price ) {
            $listing_price = houzez_clean_price_20($listing_price);
            
            $currency_maker = currency_maker();

            $listings_currency = $currency_maker['currency'];
            $price_decimals = $currency_maker['decimals'];
            $listing_currency_pos = $currency_maker['currency_position'];
            $price_thousands_separator = $currency_maker['thousands_separator'];
            $price_decimal_point_separator = $currency_maker['decimal_point_separator'];
        
            $short_prices = houzez_option('short_prices');

            if($short_prices != 1 ) {

                $cookie_currency_or_international = 0;
                $listing_price = doubleval( $listing_price );
                if(!isset($_COOKIE[ "houzez_set_current_currency" ])){
                    if(isset($_GET['international'])) {
                        $cookie_currency_or_international = 1;
                    }
                }else{
                    $cookie_currency_or_international = 1;
                }


                if ( class_exists( 'FCC_Rates' ) && houzez_currency_switcher_enabled() && $cookie_currency_or_international == 1 ) {
                    $listing_price = apply_filters( 'houzez_currency_switcher_filter', $listing_price );
                    if(isset($_GET['international'])) {
                        $listing_price = '$'. str_replace('$', '', $listing_price);
                    }
                    return $listing_price;
                }
                
                $indian_format = houzez_option('indian_format');
                if($indian_format == 1) {
                    $final_price = houzez_moneyFormatIndia ($listing_price);
                } else {
                    //number_format() — Format a number with grouped thousands
                    $final_price = number_format ( $listing_price , $price_decimals , $price_decimal_point_separator , $price_thousands_separator );
                }


            } else {
                $final_price = houzez_number_shorten($listing_price, $price_decimals);
            }
            if(  $listing_currency_pos == 'before' ) {
                return $listings_currency . $final_price;
            } else {
                return $final_price . $listings_currency;
            }

        } else {
            $listings_currency = '';
        }

        return $listings_currency;
    }
}


