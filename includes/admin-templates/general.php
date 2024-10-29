<?php
/**
* General Options
*/

if ( ! defined( "ABSPATH" ) ) exit;

$license_class = $GLOBALS['badgeos_rcp_options']->get_license_class();
?>
<div id="bosrcp-license-options">
	<form method="POST">

        <h2><?php _e( 'License Configuration', BOSRCP_LANG ); ?></h2>
        <h3><?php _e( 'Please enter the license key for this product to get automatic updates. You were emailed the license key when you purchased this item', BOSRCP_LANG ); ?></h3>
        <table class="form-table">
            <tr>
                <th style="width:100px;"><label
                            for="<?php echo $license_class->get_license_key_field(); ?>"><?php _e( 'License Key', BOSRCP_LANG ); ?></label>
                </th>
                <td>
                    <input class="regular-text" type="text" id="<?php echo $license_class->get_license_key_field(); ?>"
                           placeholder="Enter license key provided with plugin"
                           name="<?php echo $license_class->get_license_key_field(); ?>"
                           value="<?php echo get_option( 'wn_bosrcp_license_key' ); ?>"
                        <?php echo ( $license_class->get_license_handler()->is_active() ) ? 'readonly' : ''; ?>>
                </td>
            </tr>
        </table>
        <p class="submit">
            <?php if( ! $license_class->get_license_handler()->is_active() ) : ?>
                <input type="submit" name="bosrcp_activate_license" value="<?php _e( 'Activate', BOSRCP_LANG ); ?>"
                       class="button-primary"/>
            <?php endif; ?>

            <?php if( $license_class->get_license_handler()->is_active() ) : ?>
                <input type="submit" name="bosrcp_deactivate_license" value="<?php _e( 'Deactivate', BOSRCP_LANG ); ?>"
                       class="button-primary"/>
            <?php endif; ?>
        </p>
	</form>
</div>