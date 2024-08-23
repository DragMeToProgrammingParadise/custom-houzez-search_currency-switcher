<?php
// code will goes here
include_once get_stylesheet_directory() . '/framework/functions/price_functions.php';

/*-----------------------------------------------------------------------------------*/
// Submit Property filter
/*-----------------------------------------------------------------------------------*/
add_filter('houzez_submit_listing', 'houzez_submit_listing');

if( !function_exists('houzez_submit_listing') ) {
    function houzez_submit_listing($new_property) {

        $userID = get_current_user_id();
        $listings_admin_approved = houzez_option('listings_admin_approved');
        $edit_listings_admin_approved = houzez_option('edit_listings_admin_approved');
        $enable_paid_submission = houzez_option('enable_paid_submission');

        // Title
        if( isset( $_POST['prop_title']) ) {
            $new_property['post_title'] = sanitize_text_field( $_POST['prop_title'] );
        }

        if( $enable_paid_submission == 'membership' ) {
            $user_submit_has_no_membership = isset($_POST['user_submit_has_no_membership']) ? $_POST['user_submit_has_no_membership'] : '';
        } else {
            $user_submit_has_no_membership = 'no';
        }

        // Description
        if( isset( $_POST['prop_des'] ) ) {
            $new_property['post_content'] = wp_kses_post( wpautop( wptexturize( $_POST['prop_des'] ) ) );
        }

        $new_property['post_author'] = $userID;

        $submission_action = $_POST['action'];
        $prop_id = 0;

        if( $submission_action == 'add_property' ) {

            if( houzez_is_admin() ) {
                $new_property['post_status'] = 'publish';
            } else {
                if( $listings_admin_approved != 'yes' && ( $enable_paid_submission == 'no' || $enable_paid_submission == 'free_paid_listing' || $enable_paid_submission == 'membership' ) ) {
                    if( $user_submit_has_no_membership == 'yes' ) {
                        $new_property['post_status'] = 'draft';
                    } else {
                        $new_property['post_status'] = 'publish';
                    }
                } else {
                    if( $user_submit_has_no_membership == 'yes' && $enable_paid_submission = 'membership' ) {
                        $new_property['post_status'] = 'draft';
                    } else {
                        $new_property['post_status'] = 'pending';
                    }
                }
            }

            /*
             * Filter submission arguments before insert into database.
             */
            $new_property = apply_filters( 'houzez_before_submit_property', $new_property );
            $prop_id = wp_insert_post( $new_property );

            if( $prop_id > 0 ) {
                $submitted_successfully = true;
                if( $enable_paid_submission == 'membership'){ // update package status
                    houzez_update_package_listings( $userID );
                }
            }

        } else if( $submission_action == 'update_property' ) {

            $new_property['ID'] = intval( $_POST['prop_id'] );

            if( get_post_status( intval( $_POST['prop_id'] ) ) == 'draft' ) {
                if( $enable_paid_submission == 'membership') {
                    houzez_update_package_listings($userID);
                }
                if( $listings_admin_approved != 'yes' && ( $enable_paid_submission == 'no' || $enable_paid_submission == 'free_paid_listing' || $enable_paid_submission == 'membership' ) ) {
                    $new_property['post_status'] = 'publish';
                } else {
                    $new_property['post_status'] = 'pending';
                }
            } elseif( $edit_listings_admin_approved == 'yes' ) {
                    $new_property['post_status'] = 'pending';
            }

            if( ! houzez_user_has_membership($userID) && $enable_paid_submission == 'membership' ) {
                $new_property['post_status'] = 'draft';

            }

            if( houzez_is_admin() ) {
                $new_property['post_status'] = 'publish';
            }

            /*
             * Filter submission arguments before update property.
             */
            $new_property = apply_filters( 'houzez_before_update_property', $new_property );
            $prop_id = wp_update_post( $new_property );

        }

        if( $prop_id > 0 ) {


            if(class_exists('Houzez_Fields_Builder')) {
                $fields_array = Houzez_Fields_Builder::get_form_fields();
                if(!empty($fields_array)):
                    foreach ( $fields_array as $value ):
                        $field_name = $value->field_id;
                        $field_type = $value->type;

                        if( isset( $_POST[$field_name] ) && !empty( $_POST[$field_name] ) ) {

                            if( $field_type == 'checkbox_list' || $field_type == 'multiselect' ) {
                                delete_post_meta( $prop_id, 'fave_'.$field_name );
                                foreach ( $_POST[ $field_name ] as $value ) {
                                    add_post_meta( $prop_id, 'fave_'.$field_name, sanitize_text_field( $value ) );
                                }
                            } else {
                                update_post_meta( $prop_id, 'fave_'.$field_name, sanitize_text_field( $_POST[$field_name] ) );
                            }

                        } else {
                            delete_post_meta( $prop_id, 'fave_'.$field_name );
                        }

                    endforeach; 
                endif;
            }


            if( $user_submit_has_no_membership == 'yes' ) {
                update_user_meta( $userID, 'user_submit_has_no_membership', $prop_id );
                update_user_meta( $userID, 'user_submitted_without_membership', 'yes' );
            }

            // Add price post meta
            if( isset( $_POST['prop_price'] ) ) {
                update_post_meta( $prop_id, 'fave_property_price', sanitize_text_field( $_POST['prop_price'] ) );

                if( isset( $_POST['prop_label'] ) ) {
                    update_post_meta( $prop_id, 'fave_property_price_postfix', sanitize_text_field( $_POST['prop_label']) );
                }
            }

            // property price max
            if( isset( $_POST['prop_price_max'] ) ) {
                update_post_meta( $prop_id, 'fave_property_price_max', sanitize_text_field( $_POST['prop_price_max'] ) );
            }

            //price prefix
            if( isset( $_POST['prop_price_prefix'] ) ) {
                update_post_meta( $prop_id, 'fave_property_price_prefix', sanitize_text_field( $_POST['prop_price_prefix']) );
            }

            // Second Price
            if( isset( $_POST['prop_sec_price'] ) ) {
                update_post_meta( $prop_id, 'fave_property_sec_price', sanitize_text_field( $_POST['prop_sec_price'] ) );
            }

            // currency
            if( isset( $_POST['currency'] ) ) {
                update_post_meta( $prop_id, 'fave_currency', sanitize_text_field( $_POST['currency'] ) );
                if(class_exists('Houzez_Currencies')) {
                    $currencies = Houzez_Currencies::get_property_currency_2($prop_id, $_POST['currency']);

                    update_post_meta( $prop_id, 'fave_currency_info', $currencies );
                }
            }


            // Area Size
            if( isset( $_POST['prop_size'] ) ) {
                update_post_meta( $prop_id, 'fave_property_size', sanitize_text_field( $_POST['prop_size'] ) );
            }

            // Area Size Prefix
            if( isset( $_POST['prop_size_prefix'] ) ) {
                update_post_meta( $prop_id, 'fave_property_size_prefix', sanitize_text_field( $_POST['prop_size_prefix'] ) );
            }

            // Land Area Size
            if( isset( $_POST['prop_land_area'] ) ) {
                update_post_meta( $prop_id, 'fave_property_land', sanitize_text_field( $_POST['prop_land_area'] ) );
            }

            // Land Area Size Prefix
            if( isset( $_POST['prop_land_area_prefix'] ) ) {
                update_post_meta( $prop_id, 'fave_property_land_postfix', sanitize_text_field( $_POST['prop_land_area_prefix'] ) );
            }

            // Bedrooms
            if( isset( $_POST['prop_beds'] ) ) {
                update_post_meta( $prop_id, 'fave_property_bedrooms', sanitize_text_field( $_POST['prop_beds'] ) );
            }

            // Rooms
            if( isset( $_POST['prop_rooms'] ) ) {
                update_post_meta( $prop_id, 'fave_property_rooms', sanitize_text_field( $_POST['prop_rooms'] ) );
            }

            // Bathrooms
            if( isset( $_POST['prop_baths'] ) ) {
                update_post_meta( $prop_id, 'fave_property_bathrooms', sanitize_text_field( $_POST['prop_baths'] ) );
            }

            // Garages
            if( isset( $_POST['prop_garage'] ) ) {
                update_post_meta( $prop_id, 'fave_property_garage', sanitize_text_field( $_POST['prop_garage'] ) );
            }

            // Garages Size
            if( isset( $_POST['prop_garage_size'] ) ) {
                update_post_meta( $prop_id, 'fave_property_garage_size', sanitize_text_field( $_POST['prop_garage_size'] ) );
            }

            // Virtual Tour
            if( isset( $_POST['virtual_tour'] ) ) {
                update_post_meta( $prop_id, 'fave_virtual_tour', $_POST['virtual_tour'] );
            }

            // Year Built
            if( isset( $_POST['prop_year_built'] ) ) {
                update_post_meta( $prop_id, 'fave_property_year', sanitize_text_field( $_POST['prop_year_built'] ) );
            }

            // Property ID
            $auto_property_id = houzez_option('auto_property_id');
            if( $auto_property_id != 1 ) {
                if (isset($_POST['property_id'])) {
                    update_post_meta($prop_id, 'fave_property_id', sanitize_text_field($_POST['property_id']));
                }
            } else {
                    update_post_meta($prop_id, 'fave_property_id', $prop_id );
            }

            // Property Video Url
            if( isset( $_POST['prop_video_url'] ) ) {
                update_post_meta( $prop_id, 'fave_video_url', sanitize_text_field( $_POST['prop_video_url'] ) );
            }

            // property video image - in case of update
            $property_video_image = "";
            $property_video_image_id = 0;
            if( $submission_action == "update_property" ) {
                $property_video_image_id = get_post_meta( $prop_id, 'fave_video_image', true );
                if ( ! empty ( $property_video_image_id ) ) {
                    $property_video_image_src = wp_get_attachment_image_src( $property_video_image_id, 'houzez-property-detail-gallery' );
                    $property_video_image = $property_video_image_src[0];
                }
            }

            // clean up the old meta information related to images when property update
            if( $submission_action == "update_property" ){
                delete_post_meta( $prop_id, 'fave_property_images' );
                delete_post_meta( $prop_id, 'fave_attachments' );
                delete_post_meta( $prop_id, 'fave_agents' );
                delete_post_meta( $prop_id, 'fave_property_agency' );
                delete_post_meta( $prop_id, '_thumbnail_id' );
            }

            // Property Images
            if( isset( $_POST['propperty_image_ids'] ) ) {
                if (!empty($_POST['propperty_image_ids']) && is_array($_POST['propperty_image_ids'])) {
                    $property_image_ids = array();
                    foreach ($_POST['propperty_image_ids'] as $prop_img_id ) {
                        $property_image_ids[] = intval( $prop_img_id );
                        add_post_meta($prop_id, 'fave_property_images', $prop_img_id);
                    }

                    // featured image
                    if( isset( $_POST['featured_image_id'] ) ) {
                        $featured_image_id = intval( $_POST['featured_image_id'] );
                        if( in_array( $featured_image_id, $property_image_ids ) ) {
                            update_post_meta( $prop_id, '_thumbnail_id', $featured_image_id );

                            /* if video url is provided but there is no video image then use featured image as video image */
                            if ( empty( $property_video_image ) && !empty( $_POST['prop_video_url'] ) ) {
                                update_post_meta( $prop_id, 'fave_video_image', $featured_image_id );
                            }
                        }
                    } elseif ( ! empty ( $property_image_ids ) ) {
                        update_post_meta( $prop_id, '_thumbnail_id', $property_image_ids[0] );

                        /* if video url is provided but there is no video image then use featured image as video image */
                        if ( empty( $property_video_image ) && !empty( $_POST['prop_video_url'] ) ) {
                            update_post_meta( $prop_id, 'fave_video_image', $property_image_ids[0] );
                        }
                    }
                }
            }

            if( isset( $_POST['propperty_attachment_ids'] ) ) {
                    $property_attach_ids = array();
                    foreach ($_POST['propperty_attachment_ids'] as $prop_atch_id ) {
                        $property_attach_ids[] = intval( $prop_atch_id );
                        add_post_meta($prop_id, 'fave_attachments', $prop_atch_id);
                    }
            }
 

            // Add property type
            if( isset( $_POST['prop_type'] ) && ( $_POST['prop_type'] != '-1' ) ) {
                $type = array_map( 'intval', $_POST['prop_type'] );
                wp_set_object_terms( $prop_id, $type, 'property_type' );
            } else {
                wp_set_object_terms( $prop_id, '', 'property_type' );
            }

            // Add property status
            if( isset( $_POST['prop_status'] ) && ( $_POST['prop_status'] != '-1' ) ) {
                $prop_status = array_map( 'intval', $_POST['prop_status'] );
                wp_set_object_terms( $prop_id, $prop_status, 'property_status' );
            } else {
                wp_set_object_terms( $prop_id, '', 'property_status' );
            }

            // Add property status
            if( isset( $_POST['prop_labels'] ) ) {
                $prop_labels = array_map( 'intval', $_POST['prop_labels'] );
                wp_set_object_terms( $prop_id, $prop_labels, 'property_label' );
            } else {
                wp_set_object_terms( $prop_id, '', 'property_label' );
            }

            // Country
            if( isset( $_POST['country'] ) ) {
                $property_country = sanitize_text_field( $_POST['country'] );
                $country_id = wp_set_object_terms( $prop_id, $property_country, 'property_country' );
            } else {
                $default_country = houzez_option('default_country');
                $country_id = wp_set_object_terms( $prop_id, $default_country, 'property_country' );
            }
            
            // Postal Code
            if( isset( $_POST['postal_code'] ) ) {
                update_post_meta( $prop_id, 'fave_property_zip', sanitize_text_field( $_POST['postal_code'] ) );
            }

            
            if( isset( $_POST['locality'] ) ) {
                $property_city = sanitize_text_field( $_POST['locality'] );
                $city_id = wp_set_object_terms( $prop_id, $property_city, 'property_city' );

                $houzez_meta = array();
                $houzez_meta['parent_state'] = isset( $_POST['administrative_area_level_1'] ) ? $_POST['administrative_area_level_1'] : '';
                if( !empty( $city_id) && isset( $_POST['administrative_area_level_1'] ) ) {
                    update_option('_houzez_property_city_' . $city_id[0], $houzez_meta);
                }
            }

            if( isset( $_POST['neighborhood'] ) ) {
                $property_area = sanitize_text_field( $_POST['neighborhood'] );
                $area_id = wp_set_object_terms( $prop_id, $property_area, 'property_area' );

                $houzez_meta = array();
                $houzez_meta['parent_city'] = isset( $_POST['locality'] ) ? $_POST['locality'] : '';
                if( !empty( $area_id) && isset( $_POST['locality'] ) ) {
                    update_option('_houzez_property_area_' . $area_id[0], $houzez_meta);
                }
            }


            // Add property state
            if( isset( $_POST['administrative_area_level_1'] ) ) {
                $property_state = sanitize_text_field( $_POST['administrative_area_level_1'] );
                $state_id = wp_set_object_terms( $prop_id, $property_state, 'property_state' );

                $houzez_meta = array();
                $country_short = isset( $_POST['country'] ) ? $_POST['country'] : '';
                if(!empty($country_short)) {
                   $country_short = strtoupper($country_short); 
                } else {
                    $country_short = '';
                }
                
                $houzez_meta['parent_country'] = $country_short;
                if( !empty( $state_id) ) {
                    update_option('_houzez_property_state_' . $state_id[0], $houzez_meta);
                }
            }
           
            // Add property features
            if( isset( $_POST['prop_features'] ) ) {
                $features_array = array();
                foreach( $_POST['prop_features'] as $feature_id ) {
                    $features_array[] = intval( $feature_id );
                }
                wp_set_object_terms( $prop_id, $features_array, 'property_feature' );
            }

            // additional details
            if( isset( $_POST['additional_features'] ) ) {
                $additional_features = $_POST['additional_features'];
                if( ! empty( $additional_features ) ) {
                    update_post_meta( $prop_id, 'additional_features', $additional_features );
                    update_post_meta( $prop_id, 'fave_additional_features_enable', 'enable' );
                }
            } else {
                update_post_meta( $prop_id, 'additional_features', '' );
            }

            //Floor Plans
            if( isset( $_POST['floorPlans_enable'] ) ) {
                $floorPlans_enable = $_POST['floorPlans_enable'];
                if( ! empty( $floorPlans_enable ) ) {
                    update_post_meta( $prop_id, 'fave_floor_plans_enable', $floorPlans_enable );
                }
            }

            if( isset( $_POST['floor_plans'] ) ) {
                $floor_plans_post = $_POST['floor_plans'];
                if( ! empty( $floor_plans_post ) ) {
                    update_post_meta( $prop_id, 'floor_plans', $floor_plans_post );
                }
            } else {
                update_post_meta( $prop_id, 'floor_plans', '');
            }

            //Multi-units / Sub-properties
            if( isset( $_POST['multiUnits'] ) ) {
                $multiUnits_enable = $_POST['multiUnits'];
                if( ! empty( $multiUnits_enable ) ) {
                    update_post_meta( $prop_id, 'fave_multiunit_plans_enable', $multiUnits_enable );
                }
            }

            if( isset( $_POST['fave_multi_units'] ) ) {
                $fave_multi_units = $_POST['fave_multi_units'];
                if( ! empty( $fave_multi_units ) ) {
                    update_post_meta( $prop_id, 'fave_multi_units', $fave_multi_units );
                }
            } else {
                update_post_meta( $prop_id, 'fave_multi_units', '');
            }

            // Make featured
            if( isset( $_POST['prop_featured'] ) ) {
                $featured = intval( $_POST['prop_featured'] );
                update_post_meta( $prop_id, 'fave_featured', $featured );
            }

            // fave_loggedintoview
            if( isset( $_POST['login-required'] ) ) {
                $featured = intval( $_POST['login-required'] );
                update_post_meta( $prop_id, 'fave_loggedintoview', $featured );
            }

            // Mortgage
            if( $submission_action == 'add_property' ) {
                update_post_meta( $prop_id, 'fave_mortgage_cal', 0 );
                
            }

            // Private Note
            if( isset( $_POST['private_note'] ) ) {
                $private_note = wp_kses_post( $_POST['private_note'] );
                update_post_meta( $prop_id, 'fave_private_note', $private_note );
            }

            // disclaimer 
            if( isset( $_POST['property_disclaimer'] ) ) {
                $property_disclaimer = wp_kses_post( $_POST['property_disclaimer'] );
                update_post_meta( $prop_id, 'fave_property_disclaimer', $property_disclaimer );
            }

            //Energy Class
            if(isset($_POST['energy_class'])) {
                $energy_class = sanitize_text_field($_POST['energy_class']);
                update_post_meta( $prop_id, 'fave_energy_class', $energy_class );
            }
            if(isset($_POST['energy_global_index'])) {
                $energy_global_index = sanitize_text_field($_POST['energy_global_index']);
                update_post_meta( $prop_id, 'fave_energy_global_index', $energy_global_index );
            }
            if(isset($_POST['renewable_energy_global_index'])) {
                $renewable_energy_global_index = sanitize_text_field($_POST['renewable_energy_global_index']);
                update_post_meta( $prop_id, 'fave_renewable_energy_global_index', $renewable_energy_global_index );
            }
            if(isset($_POST['energy_performance'])) {
                $energy_performance = sanitize_text_field($_POST['energy_performance']);
                update_post_meta( $prop_id, 'fave_energy_performance', $energy_performance );
            }
            if(isset($_POST['epc_current_rating'])) {
                $epc_current_rating = sanitize_text_field($_POST['epc_current_rating']);
                update_post_meta( $prop_id, 'fave_epc_current_rating', $epc_current_rating );
            }
            if(isset($_POST['epc_potential_rating'])) {
                $epc_potential_rating = sanitize_text_field($_POST['epc_potential_rating']);
                update_post_meta( $prop_id, 'fave_epc_potential_rating', $epc_potential_rating );
            }


            // Property Payment
            if( isset( $_POST['prop_payment'] ) ) {
                $prop_payment = sanitize_text_field( $_POST['prop_payment'] );
                update_post_meta( $prop_id, 'fave_payment_status', $prop_payment );
            }


            if( isset( $_POST['fave_agent_display_option'] ) ) {

                $prop_agent_display_option = sanitize_text_field( $_POST['fave_agent_display_option'] );

                if( $prop_agent_display_option == 'agent_info' ) {

                    $prop_agent = $_POST['fave_agents'];

                    if(is_array($prop_agent)) {
                        foreach ($prop_agent as $agent) {
                            add_post_meta($prop_id, 'fave_agents', intval($agent) );
                        }
                    }
                    update_post_meta( $prop_id, 'fave_agent_display_option', $prop_agent_display_option );

                    if (houzez_is_agency()) {
                        $user_agency_id = get_user_meta( $userID, 'fave_author_agency_id', true );
                        if( !empty($user_agency_id)) {
                            update_post_meta($prop_id, 'fave_property_agency', $user_agency_id);
                        }
                    }

                } elseif( $prop_agent_display_option == 'agency_info' ) {

                    $user_agency_ids = $_POST['fave_property_agency'];

                    if (houzez_is_agency()) {
                        $user_agency_id = get_user_meta( $userID, 'fave_author_agency_id', true );
                        if( !empty($user_agency_id)) {
                            update_post_meta($prop_id, 'fave_property_agency', $user_agency_id);
                            update_post_meta($prop_id, 'fave_agent_display_option', $prop_agent_display_option);
                        } else {
                            update_post_meta( $prop_id, 'fave_agent_display_option', 'author_info' );
                        }

                    } else {

                        if(is_array($user_agency_ids)) {
                            foreach ($user_agency_ids as $agency) {
                                add_post_meta($prop_id, 'fave_property_agency', intval($agency) );
                            }
                        }
                        update_post_meta($prop_id, 'fave_agent_display_option', $prop_agent_display_option);
                    }
                    
                    
                } else {
                    update_post_meta( $prop_id, 'fave_agent_display_option', $prop_agent_display_option );
                }

            } else {

                if (houzez_is_agency()) {
                    $user_agency_id = get_user_meta( $userID, 'fave_author_agency_id', true );
                    if( !empty($user_agency_id) ) {
                        update_post_meta($prop_id, 'fave_agent_display_option', 'agency_info');
                        update_post_meta($prop_id, 'fave_property_agency', $user_agency_id);
                    } else {
                        update_post_meta( $prop_id, 'fave_agent_display_option', 'author_info' );
                    }

                } elseif(houzez_is_agent()){
                    $user_agent_id = get_user_meta( $userID, 'fave_author_agent_id', true );

                    if ( !empty( $user_agent_id ) ) {

                        update_post_meta($prop_id, 'fave_agent_display_option', 'agent_info');
                        update_post_meta($prop_id, 'fave_agents', $user_agent_id);

                    } else {
                        update_post_meta($prop_id, 'fave_agent_display_option', 'author_info');
                    }

                } else {
                    update_post_meta($prop_id, 'fave_agent_display_option', 'author_info');
                }
            }

            // Address
            if( isset( $_POST['property_map_address'] ) ) {
                update_post_meta( $prop_id, 'fave_property_map_address', sanitize_text_field( $_POST['property_map_address'] ) );
                update_post_meta( $prop_id, 'fave_property_address', sanitize_text_field( $_POST['property_map_address'] ) );
            }

            if( ( isset($_POST['lat']) && !empty($_POST['lat']) ) && (  isset($_POST['lng']) && !empty($_POST['lng'])  ) ) {
                $lat = sanitize_text_field( $_POST['lat'] );
                $lng = sanitize_text_field( $_POST['lng'] );
                $streetView = isset( $_POST['prop_google_street_view'] ) ? sanitize_text_field( $_POST['prop_google_street_view'] ) : '';
                $lat_lng = $lat.','.$lng;

                update_post_meta( $prop_id, 'houzez_geolocation_lat', $lat );
                update_post_meta( $prop_id, 'houzez_geolocation_long', $lng );
                update_post_meta( $prop_id, 'fave_property_location', $lat_lng );
                update_post_meta( $prop_id, 'fave_property_map', '1' );
                update_post_meta( $prop_id, 'fave_property_map_street_view', $streetView );

            }
            

            if( $submission_action == 'add_property' ) {
                do_action( 'houzez_after_property_submit', $prop_id );

                if( houzez_option('add_new_property') == 1 ) {
                    houzez_webhook_post( $_POST, 'houzez_add_new_property' );
                }

            } else if ( $submission_action == 'update_property' ) {
                do_action( 'houzez_after_property_update', $prop_id );

                if( houzez_option('add_new_property') == 1 ) {
                    houzez_webhook_post( $_POST, 'houzez_update_property' );
                }
            }

        return $prop_id;
        }
    }
}


/*-----------------------------------------------------------------------------------*/
// Listing price version 1
/*-----------------------------------------------------------------------------------*/
if( !function_exists('houzez_listing_price_v1') ) {
    function houzez_listing_price_v1($listing_id = '') {

        if(empty($listing_id)) {
            $listing_id = get_the_ID();
        } 
        
        $output = '';
        $sale_price     = get_post_meta( $listing_id, 'fave_property_price', true );
        $sale_price_max = get_post_meta($listing_id, 'fave_property_price_max', true);
        $second_price   = get_post_meta( $listing_id, 'fave_property_sec_price', true );
        $price_postfix  = get_post_meta( $listing_id, 'fave_property_price_postfix', true );
        $price_prefix   = get_post_meta( $listing_id, 'fave_property_price_prefix', true );
        $price_separator = houzez_option('currency_separator');

        $price_as_text = doubleval( $sale_price );
        if( !$price_as_text ) {
            if( is_singular( 'property' ) ) {
                $output .= '<li class="item-price item-price-text price-single-listing-text">'.$sale_price. '</li>';
                return $output;
            }
            $output .= '<li class="item-price item-price-text">'.$sale_price. '</li>';
            return $output;
        }

        if( !empty( $price_prefix ) ) {
            $price_prefix = '<span class="price-prefix">'.$price_prefix.' </span>';
        }

        if (!empty( $sale_price ) ) {

            if (!empty( $price_postfix )) {
                $price_postfix = $price_separator . $price_postfix;
            }

            if (!empty( $sale_price ) && !empty( $second_price ) ) {

                if( is_singular( 'property' ) ) {
                    $output .= '<li class="item-price">'.$price_prefix. houzez_get_property_price($sale_price);
                    if (!empty($sale_price_max)) {
                        if(is_numeric($sale_price_max)) {
                            $output .= ' - '.houzez_get_property_price($sale_price_max);
                        } else {
                            $output .= ' - '.$sale_price_max;
                        }
                    }
                    $output .= '</li>';
                    if (!empty($second_price)) {
                        $output .= '<li class="item-sub-price">';
                        $output .= houzez_get_property_price($second_price) . $price_postfix;
                        $output .= '</li>';
                    }
                } else {
                    $output .= '<li class="item-price">'.$price_prefix.' '.houzez_get_property_price($sale_price);
                    if (!empty($sale_price_max)) {
                        if(is_numeric($sale_price_max)) {
                            $output .= ' - '.houzez_get_property_price($sale_price_max);
                        } else {
                            $output .= ' - '.$sale_price_max;
                        }
                    }
                    $output .= '</li>';
                    if (!empty($second_price)) {
                        $output .= '<li class="item-sub-price">';
                        $output .= houzez_get_property_price($second_price) . $price_postfix;
                        $output .= '</li>';
                    }
                }
            } else {
                if (!empty( $sale_price )) {
                    if( is_singular( 'property' ) ) {
                        $output .= '<li class="item-price">';
                        $output .= $price_prefix. houzez_get_property_price($sale_price);
                        if (!empty($sale_price_max)) {
                            if(is_numeric($sale_price_max)) {
                                $output .= ' - '.houzez_get_property_price($sale_price_max);
                            } else {
                                $output .= ' - '.$sale_price_max;
                            }
                        }
                        $output .= $price_postfix;
                        $output .= '</li>';
                    } else {
                        $output .= '<li class="item-price">';
                        $output .= $price_prefix;
                        $output .= houzez_get_property_price($sale_price);
                        if (!empty($sale_price_max)) {
                            if(is_numeric($sale_price_max)) {
                                $output .= ' - '.houzez_get_property_price($sale_price_max);
                            } else {
                                $output .= ' - '.$sale_price_max;
                            }
                        }
                        $output .= $price_postfix;
                        $output .= '</li>';
                    }
                }
            }

        }
        return $output;
    }
}


// currency switcher 

function custom_template_part_shortcode()
{
    ob_start(); // Start output buffering
    get_template_part('template-parts/topbar/partials/currency-switcher');
    return ob_get_clean(); // Return the buffered content
}

add_shortcode('custom_currency_switcher_template', 'custom_template_part_shortcode');

// off market page dropdown 

function property_type_dropdown() {
    // Check if the property_type filter is set in the URL
    $filter = isset($_GET['property_type_filter']) ? sanitize_text_field($_GET['property_type_filter']) : 'all';

    // Query property types that have "Off Market" status
    $off_market_properties = get_posts(array(
        'post_type' => 'property',
        'posts_per_page' => -1,
        'tax_query' => array(
            array(
                'taxonomy' => 'property_status',
                'field'    => 'slug',
                'terms'    => 'off-market',
            ),
        ),
        'fields' => 'ids',
    ));

    if (!empty($off_market_properties)) {
        $terms = wp_get_object_terms($off_market_properties, 'property_type');
    } else {
        $terms = array();
    }

    // Start buffering output
    ob_start();

    // Display dropdown
    ?>
    <div class="sort-by">
        <div class="d-flex align-items-center">
            <div class="sort-by-title">
                <?php esc_html_e('Asset Type:', 'houzez'); ?>
            </div><!-- sort-by-title -->
            <select id="property-type-filter" class="selectpicker form-control bs-select-hidden" title="<?php esc_html_e('All Asset Types', 'houzez'); ?>" data-live-search="false" data-dropdown-align-right="auto">
                <option value="all"><?php esc_html_e('All Asset Types', 'houzez'); ?></option>
                <?php
                if (!is_wp_error($terms) && !empty($terms)) {
                    foreach ($terms as $term) {
                        echo '<option value="' . esc_attr($term->slug) . '" ' . selected($filter, $term->slug, false) . '>' . esc_html($term->name) . '</option>';
                    }
                }
                ?>
            </select><!-- selectpicker -->
        </div><!-- d-flex -->
    </div><!-- sort-by -->

    <script>
    // JavaScript for filtering
    document.addEventListener('DOMContentLoaded', function() {
        document.getElementById('property-type-filter').addEventListener('change', function() {
            var filter = this.value;
            var url = new URL(window.location.href);

            if (filter === 'all') {
                url.searchParams.delete('property_type_filter');
            } else {
                url.searchParams.set('property_type_filter', filter);
            }

            window.location.href = url.toString();
        });
    });
    </script>
    <?php

    // Return the buffered content
    return ob_get_clean();
}
add_shortcode('property_type_dropdown', 'property_type_dropdown');



//


function enqueue_custom_scripts()
{
    wp_enqueue_script('custom-ajax-script', get_stylesheet_directory_uri() . '/custom-ajax.js', array('jquery'), null, true);
}

add_action('wp_enqueue_scripts', 'enqueue_custom_scripts');

// get cities by country in search popup

function get_cities_by_country_callback()
{

    $selected_country = isset($_REQUEST['country']) ? sanitize_text_field($_REQUEST['country']) : '';
    $term = get_term_by('slug', strtolower($selected_country), 'property_country'); // Replace 'your_taxonomy' with the actual taxonomy name

    $current_country_slug = $term->slug;
    $options = '<option value="">City</option>';
    $option = '';

    if (!empty($selected_country)) {
        // Get the states (property_state terms) of the selected country
        $states = get_terms(array(
            'taxonomy' => 'property_state',
            'hide_empty' => false
        ));

        $all_cities = get_terms(array(
            'taxonomy' => 'property_city',
            'hide_empty' => false
        ));

        $country_cities = array();

        if (!empty($states)) {
            foreach ($states as $state) {
                $term_meta = houzez_get_property_state_meta($state->term_id);
                $parent_country = isset($term_meta['parent_country']) ? $term_meta['parent_country'] : '';

                if ($parent_country == $current_country_slug) {
                    foreach ($all_cities as $city) {
                        $city_term_meta = houzez_get_property_city_meta($city->term_id);
                        $parent_state = isset($city_term_meta['parent_state']) ? $city_term_meta['parent_state'] : '';
                        if ($parent_state == $state->slug) {
                            $country_cities[] = $city;
                            $options .= '<option data-ref="' . esc_attr($city->slug) . '" value="' . esc_attr($city->slug) . '">' . esc_html($city->name) . '</option>';
                            $option .= '<div>' . esc_html($city->name) . '</div>';
                        }
                    }
                }
            }
        }
    }

    $response_data = [
        'select' => $options,
        'div' => $option
    ];

    wp_send_json($response_data);
    wp_die();
}

add_action('wp_ajax_get_cities_by_country', 'get_cities_by_country_callback');
add_action('wp_ajax_nopriv_get_cities_by_country', 'get_cities_by_country_callback');


function custom_wp_popup_function()
{
    ob_start(); // Start output buffering

    ?>

    <!DOCTYPE html>
    <html lang="en">

    <head>


    <!-- //css-bootstrap-cdn// -->

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet"
        integrity="sha384-EVSTQN3/azprG1Anm3QDgpJLIm9Nao0Yz1ztcQTwFspd3yD65VohhpuuCOmLASjC" crossorigin="anonymous">

    <!-- //bootstrap-javascript-cdn// -->

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-MrcW6ZMFYlzcLA8Nl+NtUVF0sA7MsXsP1UyJoMp4YLEuNSfAP+JcXn/tWtIaxVXM"
        crossorigin="anonymous"></script>

    <!-- //font-awesome-cdn// -->

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css"
        integrity="sha512-DTOQO9RWCH3ppGqcWaEA1BIZOC6xxalwEsw9c2QQeAIftl+Vegovlnee1c9QX4TctnWMn13TZye+giMm8e2LwA=="
        crossorigin="anonymous" referrerpolicy="no-referrer" />

        <style>


            #model_header {
                border-bottom: none;
            }

            #model_btn:focus {
                border: none;
                outline: none;
                box-shadow: none;
            }

            #model_btn {
                color: #ba995c;
                background-color: none;
                background-image: none;
                font-size: 24px;
                padding: 0px 5px 10px 5px !important;
            }


            #exampleModal {
                transition: 0.4s ease-in;
            }

            #fst_hed {
                color: #ba995c;
                font-family: sans-serif !important;
            }

            #model_head {
                background-color: #371d39;
            }

            .nav {
                border: none;
            }

            .nav-link {
                color: white !important;
                border: none !important;
                background: none !important;
                margin: 5px;
            }

            .nav-link:focus {
                color: #ba995c !important;
                border: none !important;
                border-bottom: 2px solid #ba995c !important;
                background: none !important;
                margin: 3px;
            }

            .nav-link.active {
                color: #ba995c !important;
                border-bottom: 2px solid #ba995c !important;
            }

            .nav-link:hover {
                color: #ba995c !important;
            }

            .custom-select {
                position: relative;
                margin: 15px;
            }

            .custom-select select {
                display: none;
            }

            .select-selected:after {
                position: absolute;
                content: "";
                top: 14px;
                right: 10px;
                width: 0;
                height: 0;
                border: 6px solid #ba995c;
                border-color: #ba995c transparent transparent transparent;
                transition: 0.3s ease-in;
            }

            .select-selected.select-arrow-active:after {
                border-color: transparent transparent #ba995c transparent;
                top: 7px;
            }

            .select-items div,
            .select-selected {
                color: #ba995c;
                padding: 8px 16px;
                /* border: 1px solid #ba995c; */
                border-color: transparent transparent #ba995c transparent;
                cursor: pointer;
                user-select: none;
            }

            .select-selected {
                border-bottom: 1px solid #ba995c;
            }

            .select-items {
                /* position: absolute; */
                background-color: #fbeffc;
                top: 100%;
                left: 0;
                right: 0;
                z-index: 99;
                overflow-y: auto;
                height: 125px;
            }

            .select-items::-webkit-scrollbar {
                width: 5px;
                height: 5px;
            }

            .select-items::-webkit-scrollbar-track {
                background: transparent;
            }

            .select-items::-webkit-scrollbar-thumb {
                background: #888;
                border-radius: 10px;
            }

            .select-items::-webkit-scrollbar-thumb:hover {
                background: #555;
            }

            .select-hide {
                display: none;
            }

            .select-items div:hover,
            .same-as-selected {
                background-color: rgba(0, 0, 0, 0.1);
            }

            .apna-btn {
                display: flex;
                height: 60px;
                width: 60px;
                background-color: transparent;
            }

            .apna-btn button {
                width: 100%;
                border-radius: 50%;
                font-size: 24px;
                background: transparent;
                border: 1px solid #b7975b;
                color: #ba995c;
            }

            .apna-btn button:hover {
                color: #b7975b !important;

                border: 2px solid #b7975b !important;

            }

            .modal-backdrop {
                opacity: 0;
                position: relative;
            }

            @media screen and (max-width: 2560px) {
                #fst_hed {
                    margin-top: 10rem !important;
                }
            }

            @media screen and (max-width: 1440px) {
                #fst_hed {
                    margin-top: -2rem !important;
                }

                .wp-cus-nav {
                    margin-top: 1rem !important;
                }
            }

            .custom-select {
                height: auto !important;

                padding: 0px !important;

                background: none;
                border: none;

            }

            /* .elementor-kit-10 h1
            {


              font-size: 33px !important;
              margin-top: -36px !important;
            } */

            #model_btn:focus {

                background: none !important;
            }

            .elementor-element-0ee33b6 {
                position: absolute;
                z-index: 1000;
            }

            /* #fst_hed{
                margin-top: 2px !important;
            } */
            #wp-status-id {
                height: auto !important;
            }

            @media only screen and (max-width: 1023px) {
                /* Styles for mobile devices */
                .popup-form {
                    display: flex !important;
                    flex-direction: column;
                    align-content: center;
                    align-items: center;
                }

                .custom-select {
                    width: 60% !important; /* Adjust width for mobile devices */
                }

                .apna-btn {
                    margin-top: 10px !important;
                }
            }

            @media screen and (max-width: 425px) {
                #fst_hed {
                    font-size: 33px !important;
                    margin-top: -36px !important;
                }
            }

            .wp-cus-nav .mt5 {
                margin-top: 0px !important;
            }


            .nav-mobile {
                display: none !important;
            }

            .elementor-post__title a {
                color: #581f5b !important;
            }

            .elementor-post__read-more {
                color: #581f5b !important;
            }

            @media (max-width: 767px) {
                .elementor-location-header .e-con.e-flex {

                    --flex-wrap: nowrap !important;
                }
            }

            @media screen and (min-width: 1024px) and (max-width: 1440px) {
                #fst_hed {
                    margin-top: 1rem !important;
                    font-size: 3rem !important;
                }
            }

            @media screen and (min-width: 768px) and (max-width: 1024px) {
                #fst_hed {

                    font-size: 46px !important;
                }
            }

            @media screen and (min-width: 425px) and (max-width: 767px) {
                #fst_hed {
                    font-size: 46px !important;
                }
            }

            @media screen and (min-width: 1440px) and (max-width: 2560px) {
                #fst_hed {

                    font-size: 60px !important;

                }
            }

            /* @media only screen and (max-width: 767px) {
                .elementor-24564 .elementor-element.elementor-element-140ad9e img {
                width: 100% !important;
            }
            } */
        </style>


        
    </head>

    <body>
    <div class="container d-flex  justify-content-center mt-3 ">

        <!-- ****//model-section//**** -->

        <!-- Button trigger modal -->
        <button type="button" id="model_btn" class="btn bg-transparent" data-bs-toggle="modal"
                data-bs-target="#exampleModal">
            <i class="fa-solid fa-magnifying-glass"></i>
        </button>

        <!-- Modal -->
        <div class="modal fade w-100" id="exampleModal" tabindex="-1" aria-labelledby="exampleModalLabel"
             aria-hidden="true">
            <div class="modal-dialog modal-fullscreen">

                <div class="modal-content" id="model_head">
                    <div class="text-end m-3">
                        <button id="model_btn" type="button" class="btn-close" data-bs-dismiss="modal"
                                aria-label="Close"><i class="fa-solid fa-xmark"></i></button>
                    </div>


                    <?php

                    // $prop_state = get_terms (
                    // array(
                    // "property_area"
                    // ),
                    // array(
                    // 'orderby' => 'name',
                    // 'order' => 'ASC',
                    // 'hide_empty' => true,
                    // 'parent' => 0
                    // )
                    // );

                    // // $term_meta= get_option( "_houzez_property_city_86");
                    // //                     $parent_state = sanitize_title($term_meta['parent_state']);

                    // print_r($prop_state);

                    ?>

                    <div class="modal-body text-center">
                        <h1 id="fst_hed" class="mt-5 pt-5 display-1">
                            Property Search
                        </h1>

                        <!-- ****//tabs-section//**** -->

                        <ul class="wp-cus-nav nav nav-tabs justify-content-center mt-5 pt-4">
                            <li class="nav-item">
                                <a class="nav-link active" id="tab1-tab-cris" data-bs-toggle="tab" href="#buyhome">BUY A
                                    HOME</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" id="tab2-tab-cris" data-bs-toggle="tab" href="#renthome">RENT A
                                    HOME</a>
                            </li>

                            <li class="nav-item">
                                <a class="nav-link"
                                href="https://theglobal1.com/new-homes/">NEW HOMES</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link"
                                href="https://theglobal1.com/off-market/">OFF
                                    MARKET</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" id="tab3-tab-cris" data-bs-toggle="tab"
                                   href="#international">INTERNATIONAL</a>
                            </li>
                        </ul>

                        <div class="tab-content mt-2">

                            <div class="tab-pane fade show active" id="buyhome">
                            <form action="https://theglobal1.com/search-results/"

                                      class="popup-form d-flex justify-content-center flex-wrap mt-3">
                                    <input type="hidden" name="status[]" value="for-sale">

                                    <input type="hidden" name="country[]" value="united-kingdom">

                                    <div class="custom-select" style="width:200px;">
                                        <select name="areas[]">
                                            <option value="">Location</option>
                                            <?php
                                            $property_area = get_categories(array(
                                                'hide_empty' => 0,
                                                'taxonomy' => 'property_area',
                                            ));

                                            foreach ($property_area as $area) {
                                                ?>
                                                <option data-ref="<?php echo esc_attr($area->slug); ?>"
                                                        value="<?php echo esc_attr($area->slug); ?>"><?php echo esc_html($area->name); ?></option>
                                                <?php
                                            }
                                            ?>
                                        </select>
                                    </div>


                                    <div class="custom-select" style="width:200px;">
                                        <select name="bedrooms[]">

                                            <option value="">Bedrooms</option>

                                            <option data-ref="1" value="1">1</option>

                                            <option data-ref="2" value="2">2</option>
                                            <option data-ref="3" value="3">3</option>
                                            <option data-ref="4" value="4">4</option>
                                            <option data-ref="5" value="5">5</option>
                                            <option data-ref="6" value="6">6+</option>

                                        </select>
                                    </div>
                                    <div class="custom-select" style="width:200px;">
                                        <select name="min-price">

                                            <option value="">Min. Price</option>
                                            <option value="1000000">£1,000,000</option>
                                            <option value="2500000">£2,500,000</option>
                                            <option value="5000000">£5,000,000</option>
                                            <option value="7500000">£7,500,000</option>
                                            <option value="10000000">£10,000,000</option>
                                            <option value="15000000">£15,000,000</option>
                                            <option value="25000000">£25,000,000</option>
                                            <option value="40000000">£40,000,000</option>
                                            <option value="50000000">£50,000,000</option>
                                            <option value="75000000">£75,000,000</option>
                                            <option value="100000000">£100,000,000</option>
                                            <option value="125000000">£125,000,000</option>
                                            <option value="150000000">£150,000,000</option>
                                            <option value="200000000">£200,000,000</option>
                                            <option value="">No Min. Price</option>

                                        </select>
                                    </div>
                                    <div class="custom-select" style="width:200px;">
                                        <select name="max-price">

                                            <option value="">Max. Price</option>
                                            <option value="1000000">£1,000,000</option>
                                            <option value="2500000">£2,500,000</option>
                                            <option value="5000000">£5,000,000</option>
                                            <option value="7500000">£7,500,000</option>
                                            <option value="10000000">£10,000,000</option>
                                            <option value="15000000">£15,000,000</option>
                                            <option value="25000000">£25,000,000</option>
                                            <option value="40000000">£40,000,000</option>
                                            <option value="50000000">£50,000,000</option>
                                            <option value="75000000">£75,000,000</option>
                                            <option value="100000000">£100,000,000</option>
                                            <option value="125000000">£125,000,000</option>
                                            <option value="150000000">£150,000,000</option>
                                            <option value="200000000">£200,000,000</option>
                                            <option value="">No Max. Price</option>

                                        </select>
                                    </div>
                                    <div class="submit-btn apna-btn">
                                        <button name="submit">
                                            <i class="fa-solid fa-arrow-right"></i>
                                        </button>
                                    </div>
                                </form>
                            </div>
                            <div class="tab-pane fade" id="renthome">
                                <form action="https://theglobal1.com/search-results/"
                                      class="popup-form d-flex justify-content-center flex-wrap mt-3">
                                    <input type="hidden" name="status[]" value="for-let">

                                    <input type="hidden" name="country[]" value="united-kingdom">

                                    <div class="custom-select" style="width:200px;">
                                        <select name="label[]">


                                            <option data-ref="long-let" value="long-let">Long Let</option>
                                            <option data-ref="long-let" value="long-let">Long Let</option>
                                            <option data-ref="short-let" value="short-let">Short Let</option>

                                            <option data-ref="any" value="">Any</option>

                                        </select>
                                    </div>

                                    <div class="custom-select" style="width:200px;">
                                        <select name="areas[]">
                                            <option value="">Location</option>
                                            <?php
                                            $property_area = get_categories(array(
                                                'hide_empty' => 0,
                                                'taxonomy' => 'property_area',
                                            ));

                                            foreach ($property_area as $area) {
                                                ?>
                                                <option data-ref="<?php echo esc_attr($area->slug); ?>"
                                                        value="<?php echo esc_attr($area->slug); ?>"><?php echo esc_html($area->name); ?></option>
                                                <?php
                                            }
                                            ?>
                                        </select>
                                    </div>

                                    <div class="custom-select" style="width:200px;">
                                        <select name="bedrooms[]">

                                            <option value="">Bedrooms</option>
                                            <option data-ref="1" value="1">1</option>

                                            <option data-ref="2" value="2">2</option>
                                            <option data-ref="3" value="3">3</option>
                                            <option data-ref="4" value="4">4</option>
                                            <option data-ref="5" value="5">5</option>
                                            <option data-ref="6" value="6">6+</option>

                                        </select>
                                    </div>
                                    <div class="custom-select" style="width:200px;">
                                        <select name="min-price">

                                            <option value="">Min weekly rent</option>
                                            <option value="1000">£1,000</option>
                                            <option value="2500">£2,500</option>
                                            <option value="5000">£5,000</option>
                                            <option value="7500">£7,500</option>
                                            <option value="10000">£10,000</option>
                                            <option value="15000">£15,000</option>
                                            <option value="25000">£25,000</option>
                                            <option value="50000">£50,000</option>
                                            <option value="75000">£75,000</option>
                                            <option value="100000">£100,000</option>
                                            <option value="150000">£150,000</option>

                                            <option value="">No Min. Price</option>

                                        </select>
                                    </div>
                                    <div class="custom-select" style="width:200px;">
                                        <select name="max-price">

                                            <option value="">Max weekly rent</option>

                                            <option value="1000">£1,000</option>
                                            <option value="2500">£2,500</option>
                                            <option value="5000">£5,000</option>
                                            <option value="7500">£7,500</option>
                                            <option value="10000">£10,000</option>
                                            <option value="15000">£15,000</option>
                                            <option value="25000">£25,000</option>
                                            <option value="50000">£50,000</option>
                                            <option value="75000">£75,000</option>
                                            <option value="100000">£100,000</option>
                                            <option value="150000">£150,000</option>
                                            <option value="">No Max. Price</option>


                                        </select>
                                    </div>
                                    <div class="submit-btn apna-btn">
                                        <button name="submit">
                                            <i class="fa-solid fa-arrow-right"></i>
                                        </button>
                                    </div>
                                </form>
                            </div>
                            <div class="tab-pane fade" id="international">
                                <form action="https://theglobal1.com/search-results/"
                                      class="popup-form d-flex justify-content-center flex-wrap mt-3"
                                      id="international-form">

                                    <?php
                                    $property_country = get_categories(array(
                                        'hide_empty' => 0,
                                        'taxonomy' => 'property_country',
                                    ));
                                    ?>

                                    <input type="hidden" name="international" value="1">

                                    <div class="custom-select" style="width:200px;">
                                        <select name="country[]" id="wp-country" class="country-selector">
                                            <option value="">Country</option>
                                            <?php
                                            foreach ($property_country as $country) {
                                                ?>
                                                <option data-ref="<?php echo esc_attr($country->slug); ?>"
                                                        value="<?php echo esc_attr($country->slug); ?>"><?php echo esc_html($country->name); ?></option>
                                                <?php
                                            }
                                            ?>
                                        </select>
                                    </div>

                                    <?php
                                    $property_city = get_categories(array(
                                        'hide_empty' => 0,
                                        'taxonomy' => 'property_city',
                                    ));
                                    ?>

                                    <div class="custom-select" style="width:200px;">
                                        <select name="location[]" id="wp-cities">
                                            <option value="">City</option>
                                            <?php

                                            foreach ($property_city as $city) {
                                                ?>
                                                <option data-ref="<?php echo esc_attr($city->slug); ?>"
                                                        value="<?php echo esc_attr($city->slug); ?>"><?php echo esc_html($city->name); ?></option>
                                                <?php
                                            }
                                            ?>
                                        </select>
                                    </div>

                                    <div class="custom-select" style="width:200px;">
                                        <select name="min-price">

                                            <option value="">Min. Price</option>
                                            <option value="1000000">$1,000,000</option>
                                            <option value="2500000">$2,500,000</option>
                                            <option value="5000000">$5,000,000</option>
                                            <option value="7500000">$7,500,000</option>
                                            <option value="10000000">$10,000,000</option>
                                            <option value="15000000">$15,000,000</option>
                                            <option value="25000000">$25,000,000</option>
                                            <option value="40000000">$40,000,000</option>
                                            <option value="50000000">$50,000,000</option>
                                            <option value="75000000">$75,000,000</option>
                                            <option value="100000000">$100,000,000</option>
                                            <option value="125000000">$125,000,000</option>
                                            <option value="150000000">$150,000,000</option>
                                            <option value="200000000">$200,000,000</option>
                                            <option value="">No Min. Price</option>

                                        </select>
                                    </div>
                                    <div class="custom-select" style="width:200px;">
                                        <select name="max-price">

                                            <option value="">Max. Price</option>
                                            <option value="1000000">$1,000,000</option>
                                            <option value="2500000">$2,500,000</option>
                                            <option value="5000000">$5,000,000</option>
                                            <option value="7500000">$7,500,000</option>
                                            <option value="10000000">$10,000,000</option>
                                            <option value="15000000">$15,000,000</option>
                                            <option value="25000000">$25,000,000</option>
                                            <option value="40000000">$40,000,000</option>
                                            <option value="50000000">$50,000,000</option>
                                            <option value="75000000">$75,000,000</option>
                                            <option value="100000000">$100,000,000</option>
                                            <option value="125000000">$125,000,000</option>
                                            <option value="150000000">$150,000,000</option>
                                            <option value="200000000">$200,000,000</option>
                                            <option value="">No Max. Price</option>
                                        </select>
                                    </div>

                                    <div class="submit-btn apna-btn">
                                        <button name="button" id="international_form_btn" onclick="handleSubmit();">
                                            <i class="fa-solid fa-arrow-right"></i>
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </div>

        <!-- ****//end-model-section//**** -->
    </div>

    <!-- **//javascript-for-first-tab-active//** -->
    <script>
        document.addEventListener("DOMContentLoaded", function () {
            var tabs = document.querySelectorAll('.nav-link');
            var selects = document.querySelectorAll('.custom-select');

            tabs.forEach(function (tab, index) {
                tab.addEventListener('click', function () {
                    tabs.forEach(function (t) {
                        t.classList.remove('active');
                    });

                    tab.classList.add('active');

                    // Add a class to the first select element
                    if (index === 1) {
                        selects[0].classList.add('special-style');
                    } else {
                        selects[0].classList.remove('special-style');
                    }
                });
            });
        });
    </script>
    <script>
        document.addEventListener("DOMContentLoaded", function () {
            var tabs = document.querySelectorAll('.nav-link');

            tabs.forEach(function (tab) {
                tab.addEventListener('click', function () {
                    tabs.forEach(function (t) {
                        t.classList.remove('active');
                    });

                    tab.classList.add('active');
                });
            });
        });
    </script>

    <!-- **//javascript-for-select-options//** -->

    <script>
        var x, i, j, l, ll, selElmnt, a, b, c;
        x = document.getElementsByClassName("custom-select");
        l = x.length;

        for (i = 0; i < l; i++) {
            let city_id = "";
            selElmnt = x[i].getElementsByTagName("select")[0];
            if (selElmnt.id == 'wp-cities') {
                city_id = 'wp-cities';
            }
            ll = selElmnt.length;
            a = document.createElement("DIV");
            a.setAttribute("class", "select-selected");
            a.innerHTML = selElmnt.options[selElmnt.selectedIndex].innerHTML;
            x[i].appendChild(a);
            b = document.createElement("DIV");
            b.setAttribute("class", "select-items select-hide");
            if (city_id !== "") {
                b.setAttribute("id", "wp-city");
                a.setAttribute("id", "wp-city-select")
            }
            for (j = 1; j < ll; j++) {

                c = document.createElement("DIV");
                c.innerHTML = selElmnt.options[j].innerHTML;
                c.addEventListener("click", function (e) {

                    //console.log(this.parentNode.parentNode.getElementsByTagName("select")[0]);
                    var countrySelect = this.parentNode.parentNode.getElementsByTagName("select")[0];
                    var country = countrySelect.id;
                    var selectedValue = this.innerHTML;
                    //console.log(this.parentNode.parentNode.getElementsByTagName("select")[0].id);

                    if (country === "wp-country") {
                        // Call function from custom-ajax.js and pass selectedValue

                        jQuery.ajax({
                            url: "<?= admin_url('admin-ajax.php') ?>",
                            type: 'POST',
                            data: {
                                action: 'get_cities_by_country', // AJAX action name
                                country: selectedValue,
                            },
                            success: function (response) {
                                console.log('All citites of country selected');
                                console.log(response);
                                console.log(response.select);

                                jQuery('.custom-select #wp-cities').html(response.select);
                                //jQuery('select[name="location[]"]').html(response.select);
                                jQuery('.custom-select #wp-city').html(response.div);

//                                 jQuery('#wp-cities').selectpicker('refresh');
//                                 var citySelectElement = document.getElementById("wp-city-select");

// // Hide the element
// citySelectElement.style.display = 'none';

                                jQuery("#wp-cities").change(function () {
                                    //rehide content on change
                                    // $('.dropdown-content-bond').hide();
                                    //unhides current item
                                    var currentIndex = jQuery(this).prop('selectedIndex');
                                    console.log(currentIndex);
                                    console.log(this);
                                    // $(".dropdown-content-bond").eq( currentIndex ).show(); 
                                });

                                jQuery(document).find('.custom-select #wp-city div').on('click', function () {


                                    jQuery('.custom-select #wp-city div').removeClass('same-as-selected');
                                    jQuery(this).addClass('same-as-selected');
                                    jQuery('#wp-city-select').text(jQuery(this).text()); // Update select-selected


                                });
                            }
                        });

                    }

                    // var citySelect = this.parentNode.parentNode.getElementsByTagName("select")[0];
                    // var city = citySelect.id;
                    // var citySelectedValue = this.innerHTML;
                    // if (city === "wp-cities") {
                    //     jQuery('#wp-cities').val(citySelectedValue);
                    //     jQuery('#wp-cities').trigger('change');
                    //     console.log(citySelectedValue);


                    // }


                    var y, i, k, s, h, sl, yl;
                    s = this.parentNode.parentNode.getElementsByTagName("select")[0];
                    sl = s.length;
                    h = this.parentNode.previousSibling;
                    for (i = 0; i < sl; i++) {
                        if (s.options[i].innerHTML == this.innerHTML) {
                            s.selectedIndex = i;
                            h.innerHTML = this.innerHTML;
                            y = this.parentNode.getElementsByClassName("same-as-selected");
                            yl = y.length;
                            for (k = 0; k < yl; k++) {
                                y[k].removeAttribute("class");
                            }
                            this.setAttribute("class", "same-as-selected");
                            break;
                        }
                    }
                    h.click();
                });
                b.appendChild(c);
            }
            x[i].appendChild(b);
            a.addEventListener("click", function (e) {
                e.stopPropagation();
                closeAllSelect(this);
                this.nextSibling.classList.toggle("select-hide");
                this.classList.toggle("select-arrow-active");
            });
        }

        function closeAllSelect(elmnt) {
            var x, y, i, xl, yl, arrNo = [];
            x = document.getElementsByClassName("select-items");
            y = document.getElementsByClassName("select-selected");
            xl = x.length;
            yl = y.length;
            for (i = 0; i < yl; i++) {
                if (elmnt == y[i]) {
                    arrNo.push(i)
                } else {
                    y[i].classList.remove("select-arrow-active");
                }
            }
            for (i = 0; i < xl; i++) {
                if (arrNo.indexOf(i)) {
                    x[i].classList.add("select-hide");
                }
            }
        }

        document.addEventListener("click", closeAllSelect);
    </script>

    <!-- **//javascript-submit-btn//** -->

    <script type="text/javascript">
        const btn = document.querySelector("#btn");
        const btnText = document.querySelector("#btnText");

        // btn.onclick = () => {
        //     btnText.innerHTML = "Thanks";
        //     btn.classList.add("active");
        // };
        // Get the form element
        var apnaForm = document.getElementById('international-form');

        // Function to prevent form submission and log form values
        function handleSubmit() {
            // Get all form inputs
            var formInputs = apnaForm.querySelectorAll('input, select');

            // Log each form input value
            formInputs.forEach(function (input) {
                console.log(input.name + ': ' + input.value);
            });

// Example usage:
            var selectElement = document.getElementById('wp-cities');
            var searchText = document.getElementById('wp-city-select').textContent.trim();
            var option = searchOptionByText(selectElement, searchText);
            if (option) {
                console.log('Option found with text:', searchText);
                console.log('Value:', option.value);
                console.log('Data-ref attribute:', option.getAttribute('data-ref'));
                jQuery("#wp-cities").val(option.getAttribute('data-ref'));
                apnaForm.submit();
            } else {
                console.log('Option with text', searchText, 'not found.');
            }

        }

        // Function to search for an option by its text content
        function searchOptionByText(selectElement, searchText) {
            for (var i = 0; i < selectElement.options.length; i++) {
                if (selectElement.options[i].textContent === searchText) {
                    return selectElement.options[i];
                }
            }
            return null; // Return null if option with searchText is not found
        }
       
    </script>
    </body>

    </html>


    <?php
    return ob_get_clean(); // End output buffering and return the content
}

add_shortcode('custom_wp_popup', 'custom_wp_popup_function');




// Enqueue Locomotive Scroll CSS
function enqueue_locomotive_scroll_css() {
    wp_enqueue_style('locomotive-scroll-css', get_stylesheet_directory_uri() . '/locomotive-scroll.css');

}
add_action('wp_enqueue_scripts', 'enqueue_locomotive_scroll_css');

// Enqueue Locomotive Scroll JS
function enqueue_locomotive_scroll_js() {
    wp_enqueue_script('locomotive-scroll-js', get_stylesheet_directory_uri() . '/locomotive-scroll.min.js', array('jquery'), '', true);
}
add_action('wp_enqueue_scripts', 'enqueue_locomotive_scroll_js');

// Initialize Locomotive Scroll
function initialize_locomotive_scroll() {
    ?>
    <script>
        window.onload = function () {
            const container = document.querySelector('[data-scroll-container]');
            //console.log(container); // Check if the container is selected properly
            if (container) {
                const locomotiveScroll = new LocomotiveScroll({
                    el: container,
                    smooth: true,
                    direction: 'vertical',
                    scrollFromAnywhere: true,
                    offset: ['100%', '100%'],
                    lerp: 0.3,
                    smoothMobile: 1,
                    smoothScrolling: true,
                    //reloadOnContextChange: true,
                    //resetNativeScroll: false,
                    //smoothClass: 'has-scroll-smooth',
                    
                    // You can add more options here as needed
                });
            }
        }
    </script>
    <?php
}
add_action('wp_footer', 'initialize_locomotive_scroll');