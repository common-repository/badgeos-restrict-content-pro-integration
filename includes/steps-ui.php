<?php
/**
 * Custom Achievement Steps UI.
 *
 * @package BadgeOS Restrict Content Pro Integration
 * @subpackage Achievements
 * @author Credly, LLC
 * @license http://www.gnu.org/licenses/agpl.txt GNU AGPL v3.0
 * @link https://credly.com
 */

/**
 * Update badgeos_get_step_requirements to include our custom requirements.
 *
 * @param $requirements
 * @param $step_id
 * @return mixed
 */
function badgeos_rcp_step_requirements( $requirements, $step_id ) {

	/**
     * Add our new requirements to the list
     */
	$requirements[ 'rcp_trigger' ] = get_post_meta( $step_id, '_badgeos_rcp_trigger', true );
	$requirements[ 'rcp_object_id' ] = (int) get_post_meta( $step_id, '_badgeos_rcp_object_id', true );

	return $requirements;
}
add_filter( 'badgeos_get_deduct_step_requirements', 'badgeos_rcp_step_requirements', 10, 2 );
add_filter( 'badgeos_get_rank_req_step_requirements', 'badgeos_rcp_step_requirements', 10, 2 );
add_filter( 'badgeos_get_award_step_requirements', 'badgeos_rcp_step_requirements', 10, 2 );
add_filter( 'badgeos_get_step_requirements', 'badgeos_rcp_step_requirements', 10, 2 );

/**
 * Filter the BadgeOS Triggers selector with our own options.
 *
 * @param $triggers
 * @return mixed
 */
function badgeos_rcp_activity_triggers( $triggers ) {

	$triggers[ 'rcp_trigger' ] = __( 'Restrict Content Pro Activity', BOSRCP_LANG );
	return $triggers;
} 
add_filter( 'badgeos_activity_triggers', 'badgeos_rcp_activity_triggers', 15 );
add_filter( 'badgeos_award_points_activity_triggers', 'badgeos_rcp_activity_triggers', 15 );
add_filter( 'badgeos_deduct_points_activity_triggers', 'badgeos_rcp_activity_triggers', 15 );
add_filter( 'badgeos_ranks_req_activity_triggers', 'badgeos_rcp_activity_triggers', 15 );


/**
 * Add Restrict Content Pro Triggers selector to the Steps UI.
 *
 * @param $step_id
 * @param $post_id
 */
function badgeos_rcp_step_rcp_trigger_select( $step_id, $post_id ) {

	/**
     * Setup our select input
     */
	echo '<select name="rcp_trigger" class="select-rcp-trigger">';
	echo '<option value="">' . __( 'Select a Restrict Content Pro Trigger', BOSRCP_LANG ) . '</option>';

	/**
     * Loop through all of our rcp trigger groups
     */
	$current_trigger = get_post_meta( $step_id, '_badgeos_rcp_trigger', true );

	$rcp_triggers = $GLOBALS[ 'badgeos_rcp' ]->triggers;

	if ( !empty( $rcp_triggers ) ) {
		foreach ( $rcp_triggers as $trigger => $trigger_label ) {
			if ( is_array( $trigger_label ) ) {
				$optgroup_name = $trigger;
				$triggers = $trigger_label;

				echo '<optgroup label="' . esc_attr( $optgroup_name ) . '">';

				/**
                 * Loop through each trigger in the group
                 */
				foreach ( $triggers as $trigger_hook => $trigger_name ) {
					echo '<option' . selected( $current_trigger, $trigger_hook, false ) . ' value="' . esc_attr( $trigger_hook ) . '">' . esc_html( $trigger_name ) . '</option>';
				}
				echo '</optgroup>';
			} else {
				echo '<option' . selected( $current_trigger, $trigger, false ) . ' value="' . esc_attr( $trigger ) . '">' . esc_html( $trigger_label ) . '</option>';
			}
		}
	}

	echo '</select>';

}
add_action( 'badgeos_steps_ui_html_after_trigger_type', 'badgeos_rcp_step_rcp_trigger_select', 10, 2 );
add_action( 'badgeos_award_steps_ui_html_after_achievement_type', 'badgeos_rcp_step_rcp_trigger_select', 10, 2 );
add_action( 'badgeos_deduct_steps_ui_html_after_trigger_type', 'badgeos_rcp_step_rcp_trigger_select', 10, 2 );
add_action( 'badgeos_rank_req_steps_ui_html_after_trigger_type', 'badgeos_rcp_step_rcp_trigger_select', 10, 2 );


/**
 * Add RCP selectors to the Steps UI.
 *
 * @param $step_id
 * @param $post_id
 */
function badgeos_rcp_step_etc_select( $step_id, $post_id ) {

	$current_trigger 		= get_post_meta( $step_id, '_badgeos_rcp_trigger', true );
	$current_object_id 		= (int) get_post_meta( $step_id, '_badgeos_rcp_object_id', true );

	$subscriptions 			= rcp_get_subscription_levels( 'active' );
	$paid_subscriptions 	= rcp_get_paid_levels();
	
	$free_subscriptions 	= array();
	
	foreach ($subscriptions as $key => $subscription) {
		if( $subscription->price == 0 ) {
			array_push($free_subscriptions, $subscription);
		}
	}

	/**
     * Subscription levels
     */
	echo '<select name="badgeos_rcp_membership_id" class="select-membership-id">';
	echo '<option value="">' . __( 'Any Memberbserip', BOSRCP_LANG ) . '</option>';

	/**
     * Loop through all objects
     */
	if ( !empty( $subscriptions ) ) {
		foreach ( $subscriptions as $subscription ) {
			$selected = '';

			if ( in_array( $current_trigger, array( 
				'badgeos_rcp_subscribed_any_membership', 
				'badgeos_rcp_subscribed_specific_membership', 
				'badgeos_rcp_cancelled_specific_membership',
				'badgeos_rcp_expired_membership' 
			) ) )
				$selected = selected( $current_object_id, $subscription->id, false );

			echo '<option' . $selected . ' value="' . $subscription->id . '">' . esc_html( $subscription->name ) . '</option>';
		}
	}

	echo '</select>';


	/**
     * Paid Subscription levels
     */
	echo '<select name="badgeos_paid_subscription_level_id" class="select-paid-membership-id">';
	echo '<option value="">' . __( 'Any Paid Memberbserip', BOSRCP_LANG ) . '</option>';

	/**
     * Loop through all objects
     */
	if ( !empty( $paid_subscriptions ) ) {
		foreach ( $paid_subscriptions as $subscription ) {
			$selected = '';

			if ( in_array( $current_trigger, array( 
				'badgeos_rcp_subscribed_paid_membership', 
			) ) )
				$selected = selected( $current_object_id, $subscription->id, false );

			echo '<option' . $selected . ' value="' . $subscription->id . '">' . esc_html( $subscription->name ) . '</option>';
		}
	}

	echo '</select>';


	/**
     * Paid Subscription levels
     */
	echo '<select name="badgeos_free_subscription_level_id" class="select-free-membership-id">';
	echo '<option value="">' . __( 'Any Free Memberbserip', BOSRCP_LANG ) . '</option>';

	/**
     * Loop through all objects
     */
	if ( !empty( $free_subscriptions ) ) {
		foreach ( $free_subscriptions as $subscription ) {
			$selected = '';

			if ( in_array( $current_trigger, array( 
				'badgeos_rcp_subscribed_free_membership', 
			) ) )
				$selected = selected( $current_object_id, $subscription->id, false );

			echo '<option' . $selected . ' value="' . $subscription->id . '">' . esc_html( $subscription->name ) . '</option>';
		}
	}

	echo '</select>';
}
add_action( 'badgeos_steps_ui_html_after_trigger_type', 'badgeos_rcp_step_etc_select', 10, 2 );
add_action( 'badgeos_award_steps_ui_html_after_achievement_type', 'badgeos_rcp_step_etc_select', 10, 2 );
add_action( 'badgeos_deduct_steps_ui_html_after_trigger_type', 'badgeos_rcp_step_etc_select', 10, 2 );
add_action( 'badgeos_rank_req_steps_ui_html_after_trigger_type', 'badgeos_rcp_step_etc_select', 10, 2 );



/**
 * AJAX Handler for saving all steps.
 *
 * @param $title
 * @param $step_id
 * @param $step_data
 * @return string|void
 */
function badgeos_rcp_save_step( $title, $step_id, $step_data ) {
 
	/**
     * If we're working on a RCP trigger
     */
	if ( 'rcp_trigger' == $step_data[ 'trigger_type' ] ) {

		/**
         * Update our RCP trigger post meta
         */
		update_post_meta( $step_id, '_badgeos_rcp_trigger', $step_data[ 'rcp_trigger' ] );

		/**
         * Rewrite the step title
         */
		$title = $step_data[ 'rcp_trigger_label' ];

		$object_id = 0;
		$object_arg1 = 0;

		$normal_subscriptions_hooks = array( 
			'badgeos_rcp_subscribed_any_membership', 
			'badgeos_rcp_cancelled_membership', 
			'badgeos_rcp_expired_membership', 
			'badgeos_rcp_renewed_membership' 
		);

		if ( in_array( $step_data['rcp_trigger'], $normal_subscriptions_hooks ) ) {

		    /**
             * Get Object ID
             */
			$object_id = (int) $step_data[ 'rcp_membership_id' ];

			/**
             * Set new step title
             */
			if ( empty( $object_id ) ) {
				if( $step_data['rcp_trigger'] == "badgeos_rcp_subscribed_any_membership" ) {
					$title = __( 'Subscribe Any Membership', BOSRCP_LANG );
				}

				if( $step_data['rcp_trigger'] == "badgeos_rcp_cancelled_membership" ) {
					$title = __( 'Cancel Any Membership', BOSRCP_LANG );
				}

				if( $step_data['rcp_trigger'] == "badgeos_rcp_expired_membership" ) {
					$title = __( 'Expired Any Membership', BOSRCP_LANG );
				}

				if( $step_data['rcp_trigger'] == "badgeos_rcp_renewed_membership" ) {
					$title = __( 'Renewed Any Membership', BOSRCP_LANG );
				}
				
			} else {
				$membership = rcp_get_subscription_details( $object_id );

				if( $step_data['rcp_trigger'] == "badgeos_rcp_subscribed_any_membership" ) {
					$title = sprintf( __( 'Subscribe Membership "%s"', BOSRCP_LANG ), $membership->name );
				}

				if( $step_data['rcp_trigger'] == "badgeos_rcp_cancelled_membership" ) {
					$title = sprintf( __( 'Cancel Membership "%s"', BOSRCP_LANG ), $membership->name );
				}

				if( $step_data['rcp_trigger'] == "badgeos_rcp_expired_membership" ) {
					$title = sprintf( __( 'Expired Membership "%s"', BOSRCP_LANG ), $membership->name );
				}

				if( $step_data['rcp_trigger'] == "badgeos_rcp_renewed_membership" ) {
					$title = sprintf( __( 'Renewed Membership "%s"', BOSRCP_LANG ), $membership->name );
				}
				
			}

		}
		elseif( $step_data['rcp_trigger'] == "badgeos_rcp_subscribed_free_membership" ) {

			/**
             * Get Object ID
             */
			$object_id = (int) $step_data[ 'rcp_membership_free_id' ];

			/**
             * Set new step title
             */
			if ( empty( $object_id ) ) {
				$title = sprintf( __( 'Subscribe Any Free Membership', BOSRCP_LANG ) );
			}  else {
				$membership = rcp_get_subscription_details( $object_id );
				$title = sprintf( __( 'Subscribe Free Membership "%s"', BOSRCP_LANG ), $membership->name );
			}

		}

		elseif( $step_data['rcp_trigger'] == "badgeos_rcp_subscribed_paid_membership" ) {

			/**
             * Get Object ID
             */
			$object_id = (int) $step_data[ 'rcp_membership_paid_id' ];

			/**
             * Set new step title
             */
			if ( empty( $object_id ) ) {
				$title = sprintf( __( 'Subscribe Any Paid Membership', BOSRCP_LANG ) );
			}  else {
				$membership = rcp_get_subscription_details( $object_id );
				$title = sprintf( __( 'Subscribe Paid Membership "%s"', BOSRCP_LANG ), $membership->name );
			}

		}

		/**
         * Store our Object ID in meta
         */
		update_post_meta( $step_id, '_badgeos_rcp_object_id', $object_id );
	}

	return $title;
}
add_filter( 'badgeos_save_step', 'badgeos_rcp_save_step', 10, 3 );



/**
 * Include custom JS for the BadgeOS Steps UI.
 */
function badgeos_rcp_steps_js() {
	?>
	<script type="text/javascript">
		jQuery( document ).ready( function ( $ ) { 

			var times = $( '.required-count' ).val();

            /**
             * Listen for our change to our trigger type selector
             */
			$( document ).on( 'change', '.select-trigger-type', function () {

				var trigger_type = $( this );
				var trigger_parent = trigger_type.parent();
                /**
                 * Show our group selector if we're awarding based on a specific group
                 */
				if ( 'rcp_trigger' == trigger_type.val() ) {
					trigger_type.siblings( '.select-rcp-trigger' ).show().change();
					var trigger = trigger_parent.find('.select-rcp-trigger').val();
					
					if( parseInt( times ) < 1 )
						trigger_parent.find('.required-count').val('1');//.prop('disabled', true);
				}  else {
					trigger_type.siblings( '.select-rcp-trigger' ).val('').hide().change();
					trigger_parent.find( '.input-quiz-grade' ).parent().hide();
					var fields = [
						'membership',
						'paid-membership',
						'free-membership'
					];
					$( fields ).each( function( i,field ) {
						trigger_parent.find('.select-' + field + '-id' ).hide();
					});

					trigger_parent.find( '.required-count' ).val( times );//.prop( 'disabled', false );
				}
			} );

            /**
             * Listen for our change to our trigger type selector
             */
			$( document ).on( 'change', '.select-rcp-trigger,' +
										'.select-membership-id,' +
										'.select-paid-membership-id,' +
										'.select-free-membership-id' , function () {
				badgeos_rcp_step_change( $( this ) , times);
			} );

            /**
             * Trigger a change so we properly show/hide our RCP menues
             */
			$( '.select-trigger-type' ).change();

            /**
             * Inject our custom step details into the update step action
             */
			$( document ).on( 'update_step_data', function ( event, step_details, step ) {
				step_details.rcp_trigger = $( '.select-rcp-trigger', step ).val();
				step_details.rcp_trigger_label = $( '.select-rcp-trigger option', step ).filter( ':selected' ).text();

				step_details.rcp_membership_id 		= $( '.select-membership-id', step ).val();
				step_details.rcp_membership_paid_id = $( '.select-paid-membership-id', step ).val();
				step_details.rcp_membership_free_id = $( '.select-free-membership-id', step ).val();
				
			} );

		} );

		function badgeos_rcp_step_change( $this , times) {

			var trigger_parent = $this.parent(),
				trigger_value = trigger_parent.find( '.select-rcp-trigger' ).val();
			var	trigger_parent_value = trigger_parent.find( '.select-trigger-type' ).val();

            /**
             * Any membership ( specific, renewed, expired )
             */
			trigger_parent.find( '.select-membership-id' )
				.toggle(
					( 'badgeos_rcp_subscribed_any_membership' == trigger_value
					 || 'badgeos_rcp_cancelled_membership' == trigger_value 
					 || 'badgeos_rcp_cancelled_specific_membership' == trigger_value 
					 || 'badgeos_rcp_expired_membership' == trigger_value 
					 || 'badgeos_rcp_renewed_membership' == trigger_value 
					 || 'badgeos_rcp_subscribed_specific_membership' == trigger_value )
				);

            /**
             * Paid membership specific
             */
			trigger_parent.find( '.select-paid-membership-id' )
				.toggle( 'badgeos_rcp_subscribed_paid_membership' == trigger_value );

            /**
             * Free membership specific
             */
			trigger_parent.find( '.select-free-membership-id' )
				.toggle( 'badgeos_rcp_subscribed_free_membership' == trigger_value );

           
		}
	</script>
<?php
}
add_action( 'admin_footer', 'badgeos_rcp_steps_js' );