<?php
/**
 * Custom Achievement Rules
 *
 * @package BadgeOS Restrict Content Pro Integration
 * @author WooNinjas
 * @license http://www.gnu.org/licenses/agpl.txt GNU AGPL v3.0
 * @link https://wooninjas.com
 */

/**
 * Load up our RCP triggers so we can add actions to them
 */
function badgeos_rcp_load_triggers() {

	/**
     * Grab our RCP triggers
     */
	$rcp_triggers = $GLOBALS[ 'badgeos_rcp' ]->triggers;

	if ( !empty( $rcp_triggers ) ) {
		foreach ( $rcp_triggers as $trigger => $trigger_label ) {

			if ( is_array( $trigger_label ) ) {
				$triggers = $trigger_label;

				foreach ( $triggers as $trigger_hook => $trigger_name ) {
					add_action( $trigger_hook, 'badgeos_rcp_trigger_event', 0, 20 );
					add_action( $trigger_hook, 'badgeos_rcp_trigger_award_points_event', 0, 20 );
					add_action( $trigger_hook, 'badgeos_rcp_trigger_deduct_points_event', 0, 20 );
					add_action( $trigger_hook, 'badgeos_rcp_trigger_ranks_event', 0, 20 );
					
				}
			} else {
				add_action( $trigger, 'badgeos_rcp_trigger_event', 0, 20 );
				add_action( $trigger, 'badgeos_rcp_trigger_award_points_event', 0, 20 );
				add_action( $trigger, 'badgeos_rcp_trigger_deduct_points_event', 0, 20 );
				add_action( $trigger, 'badgeos_rcp_trigger_ranks_event', 0, 20 );
			}
		}
	}
}
add_action( 'init', 'badgeos_rcp_load_triggers', 0 );



/**
 * Handle each of our RCP triggers
 */
function badgeos_rcp_trigger_event() {

	/**
     * Setup all our important variables
     */
	global $blog_id, $wpdb;

	/**
     * Setup args
     */
	$args = func_get_args();

	/**
     * Grab the current trigger
     */
	$this_trigger = current_filter();

	rcp_log( sprintf( "WOOTEST: trigger args: %s", var_export($args, true) ) );

	/**
     * Object-specific triggers
     */
	$rcp_new_memberships_triggers = array(
		'badgeos_rcp_subscribed_any_membership',
		'badgeos_rcp_subscribed_free_membership',
		'badgeos_rcp_subscribed_paid_membership'
	);

	$rcp_cancelled_memberships_triggers = array(
		'badgeos_rcp_cancelled_membership'
	);

	$rcp_expired_memberships_triggers = array(
		'badgeos_rcp_expired_membership'
	);

	$rcp_renewed_memberships_triggers = array(
		'badgeos_rcp_renewed_membership'
	);

	//$userID = get_current_user_id();

	/** 
     * Get subscription level and ID
     */
	$triggered_object_id 	= 0;

	if( in_array($this_trigger, $rcp_new_memberships_triggers) ) {
		$membership_level 		= $args[1];
		$userID 				= $membership_level->get_customer()->get_user_id();
	}
	
	if( in_array($this_trigger, array( 'badgeos_rcp_expired_membership', 'badgeos_rcp_cancelled_membership' )) ) {
		$membership_id 			= $args[1];
		$membership_level 		= rcp_get_membership( $membership_id );
		$userID 				= $membership_level->get_customer()->get_user_id();
	}

	if( $this_trigger == 'badgeos_rcp_renewed_membership' ) {
		$membership_id 			= $args[1];
		$membership_level 		= $args[2];
		$userID 				= $membership_level->get_customer()->get_user_id();
	}

	if ( is_array( $args ) && isset( $args[ 'user' ] ) ) {
		if ( is_object( $args[ 'user' ] ) ) {
			$userID = (int) $args[ 'user' ]->ID;
		} else {
			$userID = (int) $args[ 'user' ];
		}
	}

	rcp_log( sprintf( "WOOTEST: user id: %s", var_export($userID, true) ) );

	if ( empty( $userID ) ) {
		return;
	}

	$user_data = get_user_by( 'id', $userID );

	if ( empty( $user_data ) ) {
		return;
	}
	
	/**
	* Now determine if any badges are earned based on this trigger event
	 */
	$triggered_achievements = $wpdb->get_results( $wpdb->prepare( "SELECT pm.post_id FROM $wpdb->postmeta as pm inner join $wpdb->posts as p on( pm.post_id = p.ID ) WHERE p.post_status = 'publish' and pm.meta_key = '_badgeos_rcp_trigger' AND pm.meta_value = %s", $this_trigger) );

	if( count( $triggered_achievements ) > 0 ) {
		/**
		 * Update hook count for this user
		 */
		$new_count = badgeos_update_user_trigger_count( $userID, $this_trigger, $blog_id );

		/**
		 * Mark the count in the log entry
		 */
		badgeos_post_log_entry( null, $userID, null, sprintf( __( '%1$s triggered %2$s (%3$dx)', BOSRCP_LANG ), $user_data->user_login, $this_trigger, $new_count ) );
	}

	foreach ( $triggered_achievements as $achievement ) {
		$parents = badgeos_get_achievements( array( 'parent_of' => $achievement->post_id ) );
		if( count( $parents ) > 0 ) {
			if( $parents[0]->post_status == 'publish' ) {
				$awarded = badgeos_maybe_award_achievement_to_user( $achievement->post_id, $userID, $this_trigger, $blog_id, $args );
			}
		}
	}
}


/**
 * Handle community triggers for award points
 */
function badgeos_rcp_trigger_award_points_event() {
	
	/**
     * Setup all our globals
     */
	global $user_ID, $blog_id, $wpdb;

	$site_id = $blog_id;

	$args = func_get_args();
	
	/**
     * Grab our current trigger
     */
	$this_trigger = current_filter();
	
	/**
     * Grab the user ID
     */
	$user_id = badgeos_trigger_get_user_id( $this_trigger, $args );
	rcp_log( sprintf("WOOTEST: award points user id: %s", $user_id ) );
	$user_data = get_user_by( 'id', $user_id );

	/**
     * Sanity check, if we don't have a user object, bail here
     */
	if ( ! is_object( $user_data ) )
		return $args[ 0 ];
	
	/**
     * If the user doesn't satisfy the trigger requirements, bail here\
     */
	if ( ! apply_filters( 'user_deserves_point_award_trigger', true, $user_id, $this_trigger, $site_id, $args ) ) {
        return $args[ 0 ];
    }
    
	/**
     * Now determine if any badges are earned based on this trigger event
     */
	$triggered_points = $wpdb->get_results( $wpdb->prepare("
			SELECT p.ID as post_id FROM $wpdb->postmeta AS pm INNER JOIN $wpdb->posts AS p ON 
			( p.ID = pm.post_id AND pm.meta_key = '_point_trigger_type' )INNER JOIN $wpdb->postmeta AS pmtrg 
			ON ( p.ID = pmtrg.post_id AND pmtrg.meta_key = '_badgeos_rcp_trigger' ) 
			where p.post_status = 'publish' AND pmtrg.meta_value =  %s 
			",
			$this_trigger
		) );

	if( !empty( $triggered_points ) ) {
		foreach ( $triggered_points as $point ) { 

			$parent_point_id = badgeos_get_parent_id( $point->post_id );

			/**
			 * Update hook count for this user
			 */
			$new_count = badgeos_points_update_user_trigger_count( $point->post_id, $parent_point_id, $user_id, $this_trigger, $site_id, 'Award', $args );
			
			badgeos_maybe_award_points_to_user( $point->post_id, $parent_point_id , $user_id, $this_trigger, $site_id, $args );
		}
	}
}


/**
 * Handle community triggers for deduct points
 */
function badgeos_rcp_trigger_deduct_points_event( $args='' ) {
	
	/**
     * Setup all our globals
     */
	global $user_ID, $blog_id, $wpdb;

	$site_id = $blog_id;

	$args = func_get_args();

	/**
     * Grab our current trigger
     */
	$this_trigger = current_filter();

	/**
     * Grab the user ID
     */
	$user_id = badgeos_trigger_get_user_id( $this_trigger, $args );
	$user_data = get_user_by( 'id', $user_id );

	/**
     * Sanity check, if we don't have a user object, bail here
     */
	if ( ! is_object( $user_data ) ) {
        return $args[ 0 ];
    }

	/**
     * If the user doesn't satisfy the trigger requirements, bail here
     */
	if ( ! apply_filters( 'user_deserves_point_deduct_trigger', true, $user_id, $this_trigger, $site_id, $args ) ) {
        return $args[ 0 ];
    }

	/**
     * Now determine if any Achievements are earned based on this trigger event
     */
	$triggered_deducts = $wpdb->get_results( $wpdb->prepare(
        "SELECT p.ID as post_id FROM $wpdb->postmeta AS pm INNER JOIN $wpdb->posts AS p ON 
		( p.ID = pm.post_id AND pm.meta_key = '_deduct_trigger_type' )INNER JOIN $wpdb->postmeta AS pmtrg 
		ON ( p.ID = pmtrg.post_id AND pmtrg.meta_key = '_badgeos_rcp_trigger' ) 
		where p.post_status = 'publish' AND pmtrg.meta_value =  %s",
        $this_trigger
    ) );

	if( !empty( $triggered_deducts ) ) {
		foreach ( $triggered_deducts as $point ) { 
			
			$parent_point_id = badgeos_get_parent_id( $point->post_id );

			/**
             * Update hook count for this user
             */
			$new_count = badgeos_points_update_user_trigger_count( $point->post_id, $parent_point_id, $user_id, $this_trigger, $site_id, 'Deduct', $args );
			
			badgeos_maybe_deduct_points_to_user( $point->post_id, $parent_point_id , $user_id, $this_trigger, $site_id, $args );

		}
	}	
}

/**
 * Handle community triggers for ranks
 */
function badgeos_rcp_trigger_ranks_event( $args='' ) {
	
	/**
     * Setup all our globals
     */
	global $user_ID, $blog_id, $wpdb;

	$site_id = $blog_id;

	$args = func_get_args();

	/**
     * Grab our current trigger
     */
	$this_trigger = current_filter();

	
	/**
     * Grab the user ID
     */
	$user_id = badgeos_trigger_get_user_id( $this_trigger, $args );
	$user_data = get_user_by( 'id', $user_id );

	/**
     * Sanity check, if we don't have a user object, bail here
     */
	if ( ! is_object( $user_data ) )
		return $args[ 0 ];

	/**
     * If the user doesn't satisfy the trigger requirements, bail here
     */
	if ( ! apply_filters( 'badgeos_user_rank_deserves_trigger', true, $user_id, $this_trigger, $site_id, $args ) )
		return $args[ 0 ];

	/**
     * Now determine if any Achievements are earned based on this trigger event
     */
	$triggered_ranks = $wpdb->get_results( $wpdb->prepare(
							"SELECT p.ID as post_id FROM $wpdb->postmeta AS pm INNER JOIN $wpdb->posts AS p ON 
							( p.ID = pm.post_id AND pm.meta_key = '_rank_trigger_type' )INNER JOIN $wpdb->postmeta AS pmtrg 
							ON ( p.ID = pmtrg.post_id AND pmtrg.meta_key = '_badgeos_rcp_trigger' ) 
							where p.post_status = 'publish' AND pmtrg.meta_value =  %s",
							$this_trigger
						) );
	
	if( !empty( $triggered_ranks ) ) {
		foreach ( $triggered_ranks as $rank ) { 
			$parent_id = badgeos_get_parent_id( $rank->post_id );
			if( absint($parent_id) > 0) { 
				$new_count = badgeos_ranks_update_user_trigger_count( $rank->post_id, $parent_id,$user_id, $this_trigger, $site_id, $args );
				badgeos_maybe_award_rank( $rank->post_id,$parent_id,$user_id, $this_trigger, $site_id, $args );
			} 
		}
	}
}


/**
 * Check if user deserves a RCP trigger step
 *
 * @param $return
 * @param $user_id
 * @param $achievement_id
 * @param string $this_trigger
 * @param int $site_id
 * @param array $args
 * @return bool
 */
function badgeos_rcp_user_deserves_rcp_step( $return, $user_id, $achievement_id, $this_trigger = '', $site_id = 1, $args = array() ) {

    /**
     * If we're not dealing with a step, bail here
     */
	if ( 'step' != get_post_type( $achievement_id ) ) {
		return $return;
	}

	/**
     * Grab our step requirements
     */
	$requirements = badgeos_get_step_requirements( $achievement_id );

	//rcp_log( sprintf( "WOOTEST: filter all args: %s", var_export(func_get_args(), true) ) );
	/*rcp_log( sprintf( "WOOTEST: args: %s", var_export($args, true) ) );
	rcp_log( sprintf( "WOOTEST: requirements: %s", var_export($requirements, true) ));*/
	/**
     * If the step is triggered by RCP actions...
     */
	if ( 'rcp_trigger' == $requirements[ 'trigger_type' ] ) {

	    /**
         * Do not pass go until we say you can
         */
		$return = false;

		/**
         * Unsupported trigger
         */
		if ( ! isset( $GLOBALS[ 'badgeos_rcp' ]->triggers[ $this_trigger ] ) ) {
			return $return;
		}

		/**
         * RCP requirements not met yet
         */
		$rcp_triggered = false;

		/**
         * Set our main vars
         */
		$rcp_trigger = $requirements['rcp_trigger'];
		$object_id = $requirements['rcp_object_id'];


		/**
         * Object-specific triggers
         */
		$rcp_new_memberships_triggers = array(
			'badgeos_rcp_subscribed_any_membership',
			'badgeos_rcp_subscribed_free_membership',
			'badgeos_rcp_subscribed_paid_membership'
		);

		$rcp_cancelled_memberships_triggers = array(
			'badgeos_rcp_cancelled_membership'
		);

		$rcp_expired_memberships_triggers = array(
			'badgeos_rcp_expired_membership'
		);

		$rcp_renewed_memberships_triggers = array(
			'badgeos_rcp_renewed_membership'
		);



		/** 
         * Get subscription level and ID
         */
		$triggered_object_id 	= 0;

		if( in_array($rcp_trigger, $rcp_new_memberships_triggers) ) {
			$membership_level 		= $args[1];
			$membership_level_id 	= $membership_level->get_object_id();
			$triggered_object_id 	= $membership_level_id;
		}
		
		if( in_array($rcp_trigger, array( 'badgeos_rcp_expired_membership', 'badgeos_rcp_cancelled_membership' )) ) {
			$membership_id 			= $args[1];
			$membership_level 		= rcp_get_membership( $membership_id );
			$membership_level_id 	= $membership_level->get_object_id();
			$triggered_object_id 	= $membership_level_id;
		}

		if( $rcp_trigger == 'badgeos_rcp_renewed_membership' ) {
			$membership_id 			= $args[1];
			$membership_level 		= $args[2];
			$membership_level_id 	= $membership_level->get_object_id();
			$triggered_object_id 	= $membership_level_id;
		}

		/**
         * Extra arg handling for further expansion
         */
		$object_arg1 = null;

		if ( isset( $requirements['rcp_object_arg1'] ) )
			$object_arg1 = $requirements['rcp_object_arg1'];


		rcp_log( sprintf("WOOTEST: before basic logic: %s", var_export($rcp_triggered, true)) );
		
		/**
         * Use basic trigger logic if no object set
         */

		if( in_array( $rcp_trigger, $rcp_new_memberships_triggers ) 
			|| in_array( $rcp_trigger, $rcp_cancelled_memberships_triggers ) 
			|| in_array( $rcp_trigger, $rcp_expired_memberships_triggers ) 
			|| in_array( $rcp_trigger, $rcp_renewed_memberships_triggers ) 
		) {

			if( $object_id == 0 ) {
				$rcp_triggered = true;
			} else if( $object_id > 0 ) {
				if( $object_id == $triggered_object_id ) {
					$rcp_triggered = true;
				}
			}
		}		

		rcp_log( sprintf("WOOTEST: after basic logic: %s", var_export($rcp_triggered, true)) );

		/**
         * Quiz triggers
         */
		if ( $rcp_triggered && in_array( $rcp_trigger, $rcp_new_memberships_triggers ) ) {

		    /**
             * Check for fail
             */
			if ( 'badgeos_rcp_subscribed_any_membership' == $rcp_trigger ) {
				$rcp_triggered = true;
			} elseif ( 'badgeos_rcp_subscribed_free_membership' == $rcp_trigger ) {

				if( !$membership_level->is_paid() ) {
					$rcp_triggered = true;
				} else {
					$rcp_triggered = false;
				}

			} elseif( 'badgeos_rcp_subscribed_paid_membership' == $rcp_trigger ){
				
				if( $membership_level->is_paid() ) {
					$rcp_triggered = true;
				} else {
					$rcp_triggered = false;
				}

			}
		}


		rcp_log( sprintf("WOOTEST: after more logics: %s", var_export($rcp_triggered, true)) );

		/**
         * RCP requirements met
         */
		if ( $rcp_triggered ) {

			$parent_achievement = badgeos_get_parent_of_achievement( $achievement_id );
			$parent_id = $parent_achievement->ID;
			
			$user_crossed_max_allowed_earnings = badgeos_achievement_user_exceeded_max_earnings( $user_id, $parent_id );
			rcp_log( sprintf( "WOOTEST: user earnings: %s", var_export($user_crossed_max_allowed_earnings, true) ) );
			if ( ! $user_crossed_max_allowed_earnings ) {
				$minimum_activity_count = absint( get_post_meta( $achievement_id, '_badgeos_count', true ) );
				if( ! isset( $minimum_activity_count ) || empty( $minimum_activity_count ) )
					$minimum_activity_count = 1;

				$count_step_trigger = $requirements["rcp_trigger"];
				$activities = badgeos_get_user_trigger_count( $user_id, $count_step_trigger );
				$relevant_count = absint( $activities );
	
				$achievements = badgeos_get_user_achievements(
					array(
						'user_id' => absint( $user_id ),
						'achievement_id' => $achievement_id
					)
				);
	
				$total_achievments = count( $achievements );
				$used_points = intval( $minimum_activity_count ) * intval( $total_achievments );
				$remainder = intval( $relevant_count ) - $used_points;
	
				$return  = 0;
				if ( absint( $remainder ) >= $minimum_activity_count )
					$return  = $remainder;
				
				rcp_log( "WOOTEST: testing return: " . var_export($return, true) );
				return true;
			} else {

				return 0;
			}
		}
	}

	return $return;
}
add_filter( 'user_deserves_achievement', 'badgeos_rcp_user_deserves_rcp_step', 15, 6 );


/**
 * Check if user deserves a RCP trigger step
 *
 * @param $return
 * @param $user_id
 * @param $achievement_id
 * @param string $this_trigger
 * @param int $site_id
 * @param array $args
 * @return bool
 */
function badgeos_rcp_user_deserves_credit_deduct( $return, $credit_step_id, $credit_parent_id, $user_id, $this_trigger, $site_id, $args ) {

	// Grab our step requirements
	$requirements      = badgeos_get_deduct_step_requirements( $credit_step_id );
		
	// If we're not dealing with a step, bail here
	$settings = get_option( 'badgeos_settings' );
	if ( trim( $settings['points_deduct_post_type'] ) != get_post_type( $credit_step_id ) ) {
		return $return;
	}

	// If the step is triggered by RCP actions...
	if ( 'rcp_trigger' == $requirements[ 'trigger_type' ] ) {
		// Do not pass go until we say you can
		$return = false;

		// Unsupported trigger
		if ( !isset( $GLOBALS[ 'badgeos_rcp' ]->triggers[ $this_trigger ] ) ) {
			return $return;
		}

		/**
         * RCP requirements not met yet
         */
		$rcp_triggered = false;

		/**
         * Set our main vars
         */
		$rcp_trigger = $requirements['rcp_trigger'];
		$object_id = $requirements['rcp_object_id'];


		/**
         * Object-specific triggers
         */
		$rcp_new_memberships_triggers = array(
			'badgeos_rcp_subscribed_any_membership',
			'badgeos_rcp_subscribed_free_membership',
			'badgeos_rcp_subscribed_paid_membership'
		);

		$rcp_cancelled_memberships_triggers = array(
			'badgeos_rcp_cancelled_membership'
		);

		$rcp_expired_memberships_triggers = array(
			'badgeos_rcp_expired_membership'
		);

		$rcp_renewed_memberships_triggers = array(
			'badgeos_rcp_renewed_membership'
		);



		/** 
         * Get subscription level and ID
         */
		$triggered_object_id 	= 0;

		if( in_array($rcp_trigger, $rcp_new_memberships_triggers) ) {
			$membership_level 		= $args[1];
			$membership_level_id 	= $membership_level->get_object_id();
			$triggered_object_id 	= $membership_level_id;
		}
		
		if( in_array($rcp_trigger, array( 'badgeos_rcp_expired_membership', 'badgeos_rcp_cancelled_membership' )) ) {
			$membership_id 			= $args[1];
			$membership_level 		= rcp_get_membership( $membership_id );
			$membership_level_id 	= $membership_level->get_object_id();
			$triggered_object_id 	= $membership_level_id;
		}

		if( $rcp_trigger == 'badgeos_rcp_renewed_membership' ) {
			$membership_id 			= $args[1];
			$membership_level 		= $args[2];
			$membership_level_id 	= $membership_level->get_object_id();
			$triggered_object_id 	= $membership_level_id;
		}

		/**
         * Extra arg handling for further expansion
         */
		$object_arg1 = null;

		if ( isset( $requirements['rcp_object_arg1'] ) )
			$object_arg1 = $requirements['rcp_object_arg1'];


		rcp_log( sprintf("WOOTEST: before basic logic: %s", var_export($rcp_triggered, true)) );
		
		/**
         * Use basic trigger logic if no object set
         */

		if( in_array( $rcp_trigger, $rcp_new_memberships_triggers ) 
			|| in_array( $rcp_trigger, $rcp_cancelled_memberships_triggers ) 
			|| in_array( $rcp_trigger, $rcp_expired_memberships_triggers ) 
			|| in_array( $rcp_trigger, $rcp_renewed_memberships_triggers ) 
		) {

			if( $object_id == 0 ) {
				$rcp_triggered = true;
			} else if( $object_id > 0 ) {
				if( $object_id == $triggered_object_id ) {
					$rcp_triggered = true;
				}
			}
		}		

		rcp_log( sprintf("WOOTEST: after basic logic: %s", var_export($rcp_triggered, true)) );

		/**
         * Quiz triggers
         */
		if ( $rcp_triggered && in_array( $rcp_trigger, $rcp_new_memberships_triggers ) ) {

		    /**
             * Check for fail
             */
			if ( 'badgeos_rcp_subscribed_any_membership' == $rcp_trigger ) {
				$rcp_triggered = true;
			} elseif ( 'badgeos_rcp_subscribed_free_membership' == $rcp_trigger ) {

				if( !$membership_level->is_paid() ) {
					$rcp_triggered = true;
				} else {
					$rcp_triggered = false;
				}

			} elseif( 'badgeos_rcp_subscribed_paid_membership' == $rcp_trigger ){
				
				if( $membership_level->is_paid() ) {
					$rcp_triggered = true;
				} else {
					$rcp_triggered = false;
				}

			}
		}


		rcp_log( sprintf("WOOTEST: after more logics: %s", var_export($rcp_triggered, true)) );

		/**
         * RCP requirements met
         */
		if ( $rcp_triggered ) {  
			// Grab the trigger count
			$trigger_count = points_get_user_trigger_count( $credit_step_id, $user_id, $this_trigger, $site_id, 'Deduct', $args );

			// If we meet or exceed the required number of checkins, they deserve the step
			if ( 1 == $requirements[ 'count' ] || $requirements[ 'count' ] <= $trigger_count ) {
				// OK, you can pass go now
				$return = true;
			}
		}
	}
	return $return;
}
add_filter( 'badgeos_user_deserves_credit_deduct', 'badgeos_rcp_user_deserves_credit_deduct', 15, 7 );


/**
 * Check if user deserves a RCP trigger step
 *
 * @param $return
 * @param $user_id
 * @param $achievement_id
 * @param string $this_trigger
 * @param int $site_id
 * @param array $args
 * @return bool
 */
function badgeos_rcp_user_deserves_credit_award( $return, $credit_step_id, $credit_parent_id, $user_id, $this_trigger, $site_id, $args ) {
	
	// Grab our step requirements
	$requirements      = badgeos_get_award_step_requirements( $credit_step_id );
	
	// If we're not dealing with a step, bail here
	$settings = get_option( 'badgeos_settings' );
	if ( trim( $settings['points_award_post_type'] ) != get_post_type( $credit_step_id ) ) {
		return $return;
	}

	// If the step is triggered by RCP actions...
	if ( 'rcp_trigger' == $requirements[ 'trigger_type' ] ) {
		// Do not pass go until we say you can
		$return = false;

		// Unsupported trigger
		if ( !isset( $GLOBALS[ 'badgeos_rcp' ]->triggers[ $this_trigger ] ) ) {
			return $return;
		}

		/**
         * RCP requirements not met yet
         */
		$rcp_triggered = false;

		/**
         * Set our main vars
         */
		$rcp_trigger = $requirements['rcp_trigger'];
		$object_id = $requirements['rcp_object_id'];


		/**
         * Object-specific triggers
         */
		$rcp_new_memberships_triggers = array(
			'badgeos_rcp_subscribed_any_membership',
			'badgeos_rcp_subscribed_free_membership',
			'badgeos_rcp_subscribed_paid_membership'
		);

		$rcp_cancelled_memberships_triggers = array(
			'badgeos_rcp_cancelled_membership'
		);

		$rcp_expired_memberships_triggers = array(
			'badgeos_rcp_expired_membership'
		);

		$rcp_renewed_memberships_triggers = array(
			'badgeos_rcp_renewed_membership'
		);



		/** 
         * Get subscription level and ID
         */
		$triggered_object_id 	= 0;

		if( in_array($rcp_trigger, $rcp_new_memberships_triggers) ) {
			$membership_level 		= $args[1];
			$membership_level_id 	= $membership_level->get_object_id();
			$triggered_object_id 	= $membership_level_id;
		}
		
		if( in_array($rcp_trigger, array( 'badgeos_rcp_expired_membership', 'badgeos_rcp_cancelled_membership' )) ) {
			$membership_id 			= $args[1];
			$membership_level 		= rcp_get_membership( $membership_id );
			$membership_level_id 	= $membership_level->get_object_id();
			$triggered_object_id 	= $membership_level_id;
		}

		if( $rcp_trigger == 'badgeos_rcp_renewed_membership' ) {
			$membership_id 			= $args[1];
			$membership_level 		= $args[2];
			$membership_level_id 	= $membership_level->get_object_id();
			$triggered_object_id 	= $membership_level_id;
		}

		/**
         * Extra arg handling for further expansion
         */
		$object_arg1 = null;

		if ( isset( $requirements['rcp_object_arg1'] ) )
			$object_arg1 = $requirements['rcp_object_arg1'];


		rcp_log( sprintf("WOOTEST: before basic logic: %s", var_export($rcp_triggered, true)) );
		
		/**
         * Use basic trigger logic if no object set
         */

		if( in_array( $rcp_trigger, $rcp_new_memberships_triggers ) 
			|| in_array( $rcp_trigger, $rcp_cancelled_memberships_triggers ) 
			|| in_array( $rcp_trigger, $rcp_expired_memberships_triggers ) 
			|| in_array( $rcp_trigger, $rcp_renewed_memberships_triggers ) 
		) {

			if( $object_id == 0 ) {
				$rcp_triggered = true;
			} else if( $object_id > 0 ) {
				if( $object_id == $triggered_object_id ) {
					$rcp_triggered = true;
				}
			}
		}		

		rcp_log( sprintf("WOOTEST: after basic logic: %s", var_export($rcp_triggered, true)) );

		/**
         * Quiz triggers
         */
		if ( $rcp_triggered && in_array( $rcp_trigger, $rcp_new_memberships_triggers ) ) {

		    /**
             * Check for fail
             */
			if ( 'badgeos_rcp_subscribed_any_membership' == $rcp_trigger ) {
				$rcp_triggered = true;
			} elseif ( 'badgeos_rcp_subscribed_free_membership' == $rcp_trigger ) {

				if( !$membership_level->is_paid() ) {
					$rcp_triggered = true;
				} else {
					$rcp_triggered = false;
				}

			} elseif( 'badgeos_rcp_subscribed_paid_membership' == $rcp_trigger ){
				
				if( $membership_level->is_paid() ) {
					$rcp_triggered = true;
				} else {
					$rcp_triggered = false;
				}

			}
		}

		// RCP requirements met
		if ( $rcp_triggered ) {  
			// Grab the trigger count
			$trigger_count = points_get_user_trigger_count( $credit_step_id, $user_id, $this_trigger, $site_id, 'Award', $args );

			// If we meet or exceed the required number of checkins, they deserve the step
			if ( 1 == $requirements[ 'count' ] || $requirements[ 'count' ] <= $trigger_count ) {
				// OK, you can pass go now
				$return = true;
			}
		}
	}
	
	return $return;
}
add_filter( 'badgeos_user_deserves_credit_award', 'badgeos_rcp_user_deserves_credit_award', 15, 7 );


/**
 * Check if user deserves a RCP trigger step
 *
 * @param $return
 * @param $user_id
 * @param $achievement_id
 * @param string $this_trigger
 * @param int $site_id
 * @param array $args
 * @return bool
 */
function badgeos_rcp_user_deserves_rank_step( $return, $step_id, $rank_id, $user_id, $this_trigger, $site_id, $args ) {
	// Grab our step requirements
	$requirements      = badgeos_get_rank_req_step_requirements( $step_id );
	
	// If we're not dealing with a step, bail here
	$settings = get_option( 'badgeos_settings' );
	if ( trim( $settings['ranks_step_post_type'] ) != get_post_type( $step_id ) ) {
		return $return;
	}
	
	// If the step is triggered by RCP actions...
	if ( 'rcp_trigger' == $requirements[ 'trigger_type' ] ) {
		// Do not pass go until we say you can
		$return = false;
		
		// Unsupported trigger
		if ( !isset( $GLOBALS[ 'badgeos_rcp' ]->triggers[ $this_trigger ] ) ) {
			return $return;
		}

		/**
         * RCP requirements not met yet
         */
		$rcp_triggered = false;

		/**
         * Set our main vars
         */
		$rcp_trigger = $requirements['rcp_trigger'];
		$object_id = $requirements['rcp_object_id'];


		/**
         * Object-specific triggers
         */
		$rcp_new_memberships_triggers = array(
			'badgeos_rcp_subscribed_any_membership',
			'badgeos_rcp_subscribed_free_membership',
			'badgeos_rcp_subscribed_paid_membership'
		);

		$rcp_cancelled_memberships_triggers = array(
			'badgeos_rcp_cancelled_membership'
		);

		$rcp_expired_memberships_triggers = array(
			'badgeos_rcp_expired_membership'
		);

		$rcp_renewed_memberships_triggers = array(
			'badgeos_rcp_renewed_membership'
		);



		/** 
         * Get subscription level and ID
         */
		$triggered_object_id 	= 0;

		if( in_array($rcp_trigger, $rcp_new_memberships_triggers) ) {
			$membership_level 		= $args[1];
			$membership_level_id 	= $membership_level->get_object_id();
			$triggered_object_id 	= $membership_level_id;
		}
		
		if( in_array($rcp_trigger, array( 'badgeos_rcp_expired_membership', 'badgeos_rcp_cancelled_membership' )) ) {
			$membership_id 			= $args[1];
			$membership_level 		= rcp_get_membership( $membership_id );
			$membership_level_id 	= $membership_level->get_object_id();
			$triggered_object_id 	= $membership_level_id;
		}

		if( $rcp_trigger == 'badgeos_rcp_renewed_membership' ) {
			$membership_id 			= $args[1];
			$membership_level 		= $args[2];
			$membership_level_id 	= $membership_level->get_object_id();
			$triggered_object_id 	= $membership_level_id;
		}

		/**
         * Extra arg handling for further expansion
         */
		$object_arg1 = null;

		if ( isset( $requirements['rcp_object_arg1'] ) )
			$object_arg1 = $requirements['rcp_object_arg1'];


		rcp_log( sprintf("WOOTEST: before basic logic: %s", var_export($rcp_triggered, true)) );
		
		/**
         * Use basic trigger logic if no object set
         */

		if( in_array( $rcp_trigger, $rcp_new_memberships_triggers ) 
			|| in_array( $rcp_trigger, $rcp_cancelled_memberships_triggers ) 
			|| in_array( $rcp_trigger, $rcp_expired_memberships_triggers ) 
			|| in_array( $rcp_trigger, $rcp_renewed_memberships_triggers ) 
		) {

			if( $object_id == 0 ) {
				$rcp_triggered = true;
			} else if( $object_id > 0 ) {
				if( $object_id == $triggered_object_id ) {
					$rcp_triggered = true;
				}
			}
		}		

		rcp_log( sprintf("WOOTEST: after basic logic: %s", var_export($rcp_triggered, true)) );

		/**
         * Quiz triggers
         */
		if ( $rcp_triggered && in_array( $rcp_trigger, $rcp_new_memberships_triggers ) ) {

		    /**
             * Check for fail
             */
			if ( 'badgeos_rcp_subscribed_any_membership' == $rcp_trigger ) {
				$rcp_triggered = true;
			} elseif ( 'badgeos_rcp_subscribed_free_membership' == $rcp_trigger ) {

				if( !$membership_level->is_paid() ) {
					$rcp_triggered = true;
				} else {
					$rcp_triggered = false;
				}

			} elseif( 'badgeos_rcp_subscribed_paid_membership' == $rcp_trigger ){
				
				if( $membership_level->is_paid() ) {
					$rcp_triggered = true;
				} else {
					$rcp_triggered = false;
				}

			}
		}

		// RCP requirements met
		if ( $rcp_triggered ) {  
			
			// Grab the trigger count
			$trigger_count = ranks_get_user_trigger_count( $step_id, $user_id, $this_trigger, $site_id, 'Award', $args );

			// If we meet or exceed the required number of checkins, they deserve the step
			if ( 1 == $requirements[ 'count' ] || $requirements[ 'count' ] <= $trigger_count ) {
				// OK, you can pass go now
				$return = true;
			}
		}
	}
	
	return $return;
}
add_filter( 'badgeos_user_deserves_rank_step', 'badgeos_rcp_user_deserves_rank_step', 15, 7 );


/**
 * Check if user meets the rank requirement for a given rank
 *
 * @param  bool    $return         	The current status of whether or not the user deserves this rank
 * @param  integer $step_id 		The given rank's post ID
 * @param  integer $rank_id 		The given rank's post ID
 * @param  integer $user_id        	The given user's ID
 * @param  string  $this_trigger    
 * @param  string  $site_id  
 * @param  array   $args    
 * @return bool                    	Our possibly updated earning status
 */
function badgeos_rcp_user_deserves_rank_step_count_callback( $return, $step_id = 0, $rank_id = 0, $user_id = 0, $this_trigger = '', $site_id = 0, $args=array() ) {

	if( ! $return ) {
		return $return;
	}

	/**
     * Only override the $return data if we're working on a step
     */
	$settings = ( $exists = get_option( 'badgeos_settings' ) ) ? $exists : array();
	if ( trim( $settings['ranks_step_post_type'] ) == get_post_type( $step_id ) ) {
		
		if( ! empty( $this_trigger ) && array_key_exists( $this_trigger, $GLOBALS[ 'badgeos_rcp' ]->triggers ) ) {
			
			/**
			 * Get the required number of checkins for the step.
			 */
			$minimum_activity_count = absint( get_post_meta( $step_id, '_badgeos_count', true ) );

			/**
			 * Grab the relevent activity for this step
			 */
			$current_trigger = get_post_meta( $step_id, '_badgeos_rcp_trigger', true );
			$relevant_count = absint( ranks_get_user_trigger_count( $step_id, $user_id, $current_trigger, $site_id, $args ) );

			/**
			 * If we meet or exceed the required number of checkins, they deserve the step
			 */
			if ( $relevant_count >= $minimum_activity_count ) {
				$return = true;
			} else {
				$return = false;
			}
		}
	}

	return $return;
}
add_filter( 'badgeos_user_deserves_rank_step_count', 'badgeos_rcp_user_deserves_rank_step_count_callback', 10, 7 );



/**
 * Get user for a given trigger action.
 *
 * @since  1.0.0
 *
 * @param  string  $trigger Trigger name.
 * @param  array   $args    Passed trigger args.
 * @return integer          User ID.
 */
function badgeos_rcp_trigger_get_user_id( $user_id, $this_trigger, $args ) {

	/**
     * Grab our RCP triggers
     */
	$rcp_triggers = $GLOBALS[ 'badgeos_rcp' ]->triggers;

	if( array_key_exists($this_trigger, $rcp_triggers) ) {
		
		/**
	     * Object-specific triggers
	     */
		$rcp_new_memberships_triggers = array(
			'badgeos_rcp_subscribed_any_membership',
			'badgeos_rcp_subscribed_free_membership',
			'badgeos_rcp_subscribed_paid_membership'
		);

		$rcp_cancelled_memberships_triggers = array(
			'badgeos_rcp_cancelled_membership'
		);

		$rcp_expired_memberships_triggers = array(
			'badgeos_rcp_expired_membership'
		);

		$rcp_renewed_memberships_triggers = array(
			'badgeos_rcp_renewed_membership'
		);

		//$user_id = get_current_user_id();

		/** 
	     * Get subscription level and ID
	     */

		rcp_log( sprintf("WOORCP: triggers args %s: ", var_export($args, true)) );

		if( in_array($this_trigger, $rcp_new_memberships_triggers) ) {
			$membership_level 		= $args[1];
			$user_id 				= $membership_level->get_customer()->get_user_id();
		}
		
		if( in_array($this_trigger, array( 'badgeos_rcp_expired_membership', 'badgeos_rcp_cancelled_membership' )) ) {
			$membership_id 			= $args[1];
			$membership_level 		= rcp_get_membership( $membership_id );
			$user_id 				= $membership_level->get_customer()->get_user_id();
		}

		if( $this_trigger == 'badgeos_rcp_renewed_membership' ) {
			$membership_id 			= $args[1];
			$membership_level 		= $args[2];
			$user_id 				= $membership_level->get_customer()->get_user_id();
		}

		rcp_log( "WOOTEST: filter triggers user id: ". $user_id );

		return $user_id;
	}

	return $user_id;
}

add_filter( 'badgeos_trigger_get_user_id', 'badgeos_rcp_trigger_get_user_id', 99, 3 );