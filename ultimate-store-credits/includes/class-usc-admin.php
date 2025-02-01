<?php
/**
 * Admin settings page + user-credit tools + user profile readout.
 *
 * @package ultimate-store-credits
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class USC_Admin {

    public function __construct() {
        add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );

        // Tools: form handlers
        add_action( 'admin_post_usc_reset_all_credits', array( $this, 'process_reset_all_credits' ) );
        add_action( 'admin_post_usc_reset_single_user_credits', array( $this, 'process_reset_single_user_credits' ) );
        add_action( 'admin_post_usc_test_rollover', array( $this, 'process_test_rollover' ) );

        // Custom "Save Settings"
        add_action( 'admin_post_usc_save_settings', array( $this, 'process_save_settings' ) );

        // Admin notices
        add_action( 'admin_notices', array( $this, 'maybe_show_admin_notices' ) );

        // Show user credits & anniversary date in user profile
        add_action( 'show_user_profile', array( $this, 'render_user_credits_field' ) );
        add_action( 'edit_user_profile', array( $this, 'render_user_credits_field' ) );
        add_action( 'show_user_profile', array( $this, 'render_user_anniversary_field' ), 20 );
        add_action( 'edit_user_profile', array( $this, 'render_user_anniversary_field' ), 20 );
        add_action( 'personal_options_update', array( $this, 'save_user_anniversary_field' ) );
        add_action( 'edit_user_profile_update', array( $this, 'save_user_anniversary_field' ) );
    }

    public function add_admin_menu() {
        add_submenu_page(
            'woocommerce',
            __( 'Ultimate Store Credits', 'ultimate-store-credits' ),
            __( 'Store Credits', 'ultimate-store-credits' ),
            'manage_options',
            'ultimate-store-credits',
            array( $this, 'render_admin_page' )
        );
    }

    public function enqueue_admin_assets( $hook ) {
        if ( false !== strpos( $hook, 'ultimate-store-credits' ) ) {
            wp_enqueue_style(
                'usc-admin',
                USC_PLUGIN_URL . 'assets/css/usc-admin.css',
                array(),
                USC_PLUGIN_VERSION
            );
            // Color picker
            wp_enqueue_style( 'wp-color-picker' );
            wp_enqueue_script( 'wp-color-picker' );
            wp_enqueue_script(
                'usc-admin',
                USC_PLUGIN_URL . 'assets/js/usc-admin.js',
                array( 'wp-color-picker', 'jquery' ),
                USC_PLUGIN_VERSION,
                true
            );
        }
    }

    public function render_user_credits_field( $user ) {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        $credits = get_user_meta( $user->ID, 'usc_store_credits', true );
        $credits = $credits ? floatval( $credits ) : 0.0;
        ?>
        <h2><?php esc_html_e( 'Store Credits', 'ultimate-store-credits' ); ?></h2>
        <table class="form-table">
            <tr>
                <th><label><?php esc_html_e( 'Current Store Credit Balance', 'ultimate-store-credits' ); ?></label></th>
                <td>
                    <input type="text" readonly value="<?php echo esc_attr( number_format_i18n( $credits, 2 ) ); ?>" />
                    <p class="description">
                        <?php esc_html_e( 'This user’s current store-credit balance (read-only).', 'ultimate-store-credits' ); ?>
                    </p>
                </td>
            </tr>
        </table>
        <?php
    }

    public function render_user_anniversary_field( $user ) {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        $anniversary_date = get_user_meta( $user->ID, 'usc_anniversary_date', true );
        ?>
        <h2><?php esc_html_e( 'Credits Anniversary Date', 'ultimate-store-credits' ); ?></h2>
        <table class="form-table">
            <tr>
                <th><label for="usc_anniversary_date"><?php esc_html_e( 'Anniversary Date', 'ultimate-store-credits' ); ?></label></th>
                <td>
                    <input type="date"
                           name="usc_anniversary_date"
                           id="usc_anniversary_date"
                           value="<?php echo esc_attr( $anniversary_date ); ?>" />
                    <p class="description">
                        <?php esc_html_e( 'If using Anniversary-based resets, credits reset each year on this date.', 'ultimate-store-credits' ); ?>
                    </p>
                </td>
            </tr>
        </table>
        <?php
    }

    public function save_user_anniversary_field( $user_id ) {
        if ( ! current_user_can( 'manage_options', $user_id ) ) {
            return;
        }
        if ( isset( $_POST['usc_anniversary_date'] ) ) {
            $date_val = sanitize_text_field( $_POST['usc_anniversary_date'] );
            update_user_meta( $user_id, 'usc_anniversary_date', $date_val );
        }
    }

    public function render_admin_page() {
        $usc_yearly_credit_amount       = get_option( 'usc_yearly_credit_amount', 50 ); // now effectively 50 by default
        $usc_allow_partial_usage        = get_option( 'usc_allow_partial_usage', 'no' );
        $usc_yearly_credit_reset_month  = get_option( 'usc_yearly_credit_reset_month', '1' );
        $usc_yearly_credit_reset_day    = get_option( 'usc_yearly_credit_reset_day', '1' );
        $usc_partial_button_bg          = get_option( 'usc_partial_button_bg', '#0073aa' );
        $usc_partial_button_text_color  = get_option( 'usc_partial_button_text_color', '#ffffff' );
        $usc_partial_disclaimer_text    = get_option( 'usc_partial_disclaimer_text', '' );
        $usc_reset_method               = get_option( 'usc_reset_method', 'fixed' );
        $usc_allow_rollover             = get_option( 'usc_allow_rollover', 'no' );
        $usc_rollover_max               = get_option( 'usc_rollover_max', '' );
        $usc_enable_domain_restriction  = get_option( 'usc_enable_domain_restriction', 'no' );
        $usc_allowed_domain             = get_option( 'usc_allowed_domain', '' );
        ?>
        <div class="wrap usc-admin-settings">
            <h1><?php esc_html_e( 'Ultimate Store Credits Settings by Kyle Altenderfer', 'ultimate-store-credits' ); ?></h1>

            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php?action=usc_save_settings' ) ); ?>">
                <?php wp_nonce_field( 'usc_save_settings_action', 'usc_save_settings_nonce' ); ?>

                <table class="form-table">
                    <tr>
                        <th>
                            <label for="usc_yearly_credit_amount">
                                <?php esc_html_e( 'Yearly Credit Amount', 'ultimate-store-credits' ); ?>
                            </label>
                        </th>
                        <td>
                            <input type="number" step="0.01" min="0"
                                   name="usc_yearly_credit_amount"
                                   id="usc_yearly_credit_amount"
                                   value="<?php echo esc_attr( $usc_yearly_credit_amount ); ?>" />
                            <p class="description"><?php esc_html_e( 'Default is $50.', 'ultimate-store-credits' ); ?></p>
                        </td>
                    </tr>

                    <tr>
                        <th><?php esc_html_e( 'Reset Method', 'ultimate-store-credits' ); ?></th>
                        <td>
                            <label>
                                <input type="radio" name="usc_reset_method" value="fixed"
                                    <?php checked( $usc_reset_method, 'fixed' ); ?> />
                                <?php esc_html_e( 'Fixed Annual Date', 'ultimate-store-credits' ); ?>
                            </label>
                            <br/>
                            <label>
                                <input type="radio" name="usc_reset_method" value="anniversary"
                                    <?php checked( $usc_reset_method, 'anniversary' ); ?> />
                                <?php esc_html_e( 'User Anniversary Date', 'ultimate-store-credits' ); ?>
                            </label>
                            <p class="description">
                                <?php esc_html_e( 'Choose whether credits reset on a single global date or on each user’s anniversary date.', 'ultimate-store-credits' ); ?>
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <th><?php esc_html_e( 'If Fixed Method: Reset Month/Day', 'ultimate-store-credits' ); ?></th>
                        <td>
                            <label>
                                <?php esc_html_e( 'Month:', 'ultimate-store-credits' ); ?>
                                <select name="usc_yearly_credit_reset_month" id="usc_yearly_credit_reset_month">
                                    <?php for ( $m = 1; $m <= 12; $m++ ) : ?>
                                        <option value="<?php echo esc_attr( $m ); ?>"
                                            <?php selected( $usc_yearly_credit_reset_month, $m ); ?>>
                                            <?php echo esc_html( $m ); ?>
                                        </option>
                                    <?php endfor; ?>
                                </select>
                            </label>
                            <label style="margin-left:10px;">
                                <?php esc_html_e( 'Day:', 'ultimate-store-credits' ); ?>
                                <select name="usc_yearly_credit_reset_day" id="usc_yearly_credit_reset_day">
                                    <?php for ( $d = 1; $d <= 31; $d++ ) : ?>
                                        <option value="<?php echo esc_attr( $d ); ?>"
                                            <?php selected( $usc_yearly_credit_reset_day, $d ); ?>>
                                            <?php echo esc_html( $d ); ?>
                                        </option>
                                    <?php endfor; ?>
                                </select>
                            </label>
                            <p class="description">
                                <?php esc_html_e( 'If using the “Fixed” option, credits reset each year on this month/day.', 'ultimate-store-credits' ); ?>
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <th><?php esc_html_e( 'Rollover Credits?', 'ultimate-store-credits' ); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="usc_allow_rollover" value="yes"
                                    <?php checked( $usc_allow_rollover, 'yes' ); ?> />
                                <?php esc_html_e( 'Yes, allow the user’s existing credits to carry over (up to a max).', 'ultimate-store-credits' ); ?>
                            </label>
                            <p class="description">
                                <?php esc_html_e( 'If checked, new credit amounts are added to the current balance at reset (instead of replacing).', 'ultimate-store-credits' ); ?>
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <th><?php esc_html_e( 'Max Rollover Amount', 'ultimate-store-credits' ); ?></th>
                        <td>
                            <input type="number" step="0.01" min="0" name="usc_rollover_max" id="usc_rollover_max"
                                   value="<?php echo esc_attr( $usc_rollover_max ); ?>" />
                            <p class="description">
                                <?php esc_html_e( 'If rollover is enabled, the balance cannot exceed this amount. Leave blank or 0 for no cap.', 'ultimate-store-credits' ); ?>
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <th><?php esc_html_e( 'Allow Partial Usage?', 'ultimate-store-credits' ); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="usc_allow_partial_usage" value="yes"
                                    <?php checked( $usc_allow_partial_usage, 'yes' ); ?> />
                                <?php esc_html_e( 'Yes, allow partial usage of credits (via one-time coupon at checkout).', 'ultimate-store-credits' ); ?>
                            </label>
                            <p class="description">
                                <?php esc_html_e( 'If enabled, users can create a one-time coupon if their credit < total.', 'ultimate-store-credits' ); ?>
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <th><?php esc_html_e( 'Partial Usage Button BG', 'ultimate-store-credits' ); ?></th>
                        <td>
                            <input type="text" name="usc_partial_button_bg" class="usc-color-field"
                                   value="<?php echo esc_attr( $usc_partial_button_bg ); ?>" />
                            <p class="description">
                                <?php esc_html_e( 'Background color for "Apply Store Credits" button.', 'ultimate-store-credits' ); ?>
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <th><?php esc_html_e( 'Partial Usage Button Text Color', 'ultimate-store-credits' ); ?></th>
                        <td>
                            <input type="text" name="usc_partial_button_text_color" class="usc-color-field"
                                   value="<?php echo esc_attr( $usc_partial_button_text_color ); ?>" />
                            <p class="description">
                                <?php esc_html_e( 'Text color for "Apply Store Credits" button.', 'ultimate-store-credits' ); ?>
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <th><?php esc_html_e( 'Partial Disclaimer Text', 'ultimate-store-credits' ); ?></th>
                        <td>
                            <input type="text" name="usc_partial_disclaimer_text" style="width: 400px;"
                                   value="<?php echo esc_attr( $usc_partial_disclaimer_text ); ?>" />
                            <p class="description">
                                <?php esc_html_e( 'Shown below the button. E.g. "Credits are only deducted if you complete the order."', 'ultimate-store-credits' ); ?>
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <th><?php esc_html_e( 'Enable Domain Restriction?', 'ultimate-store-credits' ); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="usc_enable_domain_restriction" value="yes"
                                    <?php checked( $usc_enable_domain_restriction, 'yes' ); ?> />
                                <?php esc_html_e( 'Yes, only allow users to register with a specific email domain.', 'ultimate-store-credits' ); ?>
                            </label>
                            <p class="description">
                                <?php esc_html_e( 'If checked, new registrations must use the domain specified below.', 'ultimate-store-credits' ); ?>
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <th><?php esc_html_e( 'Allowed Domain', 'ultimate-store-credits' ); ?></th>
                        <td>
                            <input type="text" name="usc_allowed_domain" value="<?php echo esc_attr( $usc_allowed_domain ); ?>" />
                            <p class="description">
                                <?php esc_html_e( 'Enter the domain portion only (e.g. example.com).', 'ultimate-store-credits' ); ?>
                            </p>
                        </td>
                    </tr>
                </table>

                <p>
                    <button type="submit" class="button button-primary">
                        <?php esc_html_e( 'Save Settings', 'ultimate-store-credits' ); ?>
                    </button>
                </p>
            </form>

            <hr />

            <h2><?php esc_html_e( 'Tools', 'ultimate-store-credits' ); ?></h2>
            <p><?php esc_html_e( 'Use these tools to manually reset or test store credits.', 'ultimate-store-credits' ); ?></p>

            <!-- Reset ALL -->
            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                <?php wp_nonce_field( 'usc_reset_all_credits_action', 'usc_reset_all_credits_nonce' ); ?>
                <input type="hidden" name="action" value="usc_reset_all_credits" />
                <p>
                    <input type="submit" class="button button-primary"
                           value="<?php esc_attr_e( 'Reset Credits for ALL Users', 'ultimate-store-credits' ); ?>"
                           onclick="return confirm('<?php esc_attr_e( 'Are you sure? This will override all users’ balances.', 'ultimate-store-credits' ); ?>');" />
                </p>
            </form>

            <!-- Reset SINGLE user -->
            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                <?php wp_nonce_field( 'usc_reset_single_user_credits_action', 'usc_reset_single_user_credits_nonce' ); ?>
                <input type="hidden" name="action" value="usc_reset_single_user_credits" />
                <table class="form-table">
                    <tr>
                        <th>
                            <label for="usc_user_identifier"><?php esc_html_e( 'User ID or Email', 'ultimate-store-credits' ); ?></label>
                        </th>
                        <td>
                            <input type="text" name="usc_user_identifier" id="usc_user_identifier" value="" />
                            <p class="description">
                                <?php esc_html_e( 'Enter user ID or email to reset that user’s credits.', 'ultimate-store-credits' ); ?>
                            </p>
                        </td>
                    </tr>
                </table>
                <p>
                    <input type="submit" class="button button-secondary"
                           value="<?php esc_attr_e( 'Reset Single User Credits', 'ultimate-store-credits' ); ?>" />
                </p>
            </form>

            <!-- Test Rollover -->
            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                <?php wp_nonce_field( 'usc_test_rollover_action', 'usc_test_rollover_nonce' ); ?>
                <input type="hidden" name="action" value="usc_test_rollover" />
                <table class="form-table">
                    <tr>
                        <th>
                            <label for="usc_test_user_identifier"><?php esc_html_e( 'User ID or Email', 'ultimate-store-credits' ); ?></label>
                        </th>
                        <td>
                            <input type="text" name="usc_test_user_identifier" id="usc_test_user_identifier" value="" />
                            <p class="description">
                                <?php esc_html_e( 'Simulate how that user’s credits would change in a rollover.', 'ultimate-store-credits' ); ?>
                            </p>
                        </td>
                    </tr>
                </table>
                <p>
                    <!-- Changed class="button" to class="button button-secondary" to match dark style -->
                    <input type="submit" class="button button-secondary"
                           value="<?php esc_attr_e( 'Test Rollover', 'ultimate-store-credits' ); ?>" />
                </p>
            </form>
        </div>
        <?php
    }

    public function process_save_settings() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'Not allowed.', 'ultimate-store-credits' ) );
        }
        if ( ! isset( $_POST['usc_save_settings_nonce'] ) ||
             ! wp_verify_nonce( $_POST['usc_save_settings_nonce'], 'usc_save_settings_action' ) ) {
            wp_die( __( 'Security check failed.', 'ultimate-store-credits' ) );
        }

        $yearly_amount = isset( $_POST['usc_yearly_credit_amount'] ) ? floatval( $_POST['usc_yearly_credit_amount'] ) : 400;
        update_option( 'usc_yearly_credit_amount', $yearly_amount );

        $allow_partial = ( ! empty( $_POST['usc_allow_partial_usage'] ) && 'yes' === $_POST['usc_allow_partial_usage'] ) ? 'yes' : 'no';
        update_option( 'usc_allow_partial_usage', $allow_partial );

        $reset_method = ( isset( $_POST['usc_reset_method'] ) && 'anniversary' === $_POST['usc_reset_method'] ) ? 'anniversary' : 'fixed';
        update_option( 'usc_reset_method', $reset_method );

        $reset_month = isset( $_POST['usc_yearly_credit_reset_month'] ) ? absint( $_POST['usc_yearly_credit_reset_month'] ) : 1;
        update_option( 'usc_yearly_credit_reset_month', $reset_month );

        $reset_day = isset( $_POST['usc_yearly_credit_reset_day'] ) ? absint( $_POST['usc_yearly_credit_reset_day'] ) : 1;
        update_option( 'usc_yearly_credit_reset_day', $reset_day );

        $allow_rollover = ( ! empty( $_POST['usc_allow_rollover'] ) && 'yes' === $_POST['usc_allow_rollover'] ) ? 'yes' : 'no';
        update_option( 'usc_allow_rollover', $allow_rollover );

        $rollover_max = isset( $_POST['usc_rollover_max'] ) ? floatval( $_POST['usc_rollover_max'] ) : '';
        update_option( 'usc_rollover_max', $rollover_max );

        $partial_bg = isset( $_POST['usc_partial_button_bg'] ) ? sanitize_hex_color( $_POST['usc_partial_button_bg'] ) : '#0073aa';
        update_option( 'usc_partial_button_bg', $partial_bg );

        $partial_text = isset( $_POST['usc_partial_button_text_color'] ) ? sanitize_hex_color( $_POST['usc_partial_button_text_color'] ) : '#ffffff';
        update_option( 'usc_partial_button_text_color', $partial_text );

        $disclaimer = isset( $_POST['usc_partial_disclaimer_text'] ) ? sanitize_text_field( $_POST['usc_partial_disclaimer_text'] ) : '';
        update_option( 'usc_partial_disclaimer_text', $disclaimer );

        $enable_domain = ( ! empty( $_POST['usc_enable_domain_restriction'] ) && 'yes' === $_POST['usc_enable_domain_restriction'] ) ? 'yes' : 'no';
        update_option( 'usc_enable_domain_restriction', $enable_domain );

        $allowed_domain = isset( $_POST['usc_allowed_domain'] ) ? sanitize_text_field( $_POST['usc_allowed_domain'] ) : '';
        update_option( 'usc_allowed_domain', $allowed_domain );

        wp_safe_redirect( add_query_arg( 'usc_message', 'settings_saved', admin_url( 'admin.php?page=ultimate-store-credits' ) ) );
        exit;
    }

    public function process_reset_all_credits() {
        if ( ! isset( $_POST['usc_reset_all_credits_nonce'] ) ||
             ! wp_verify_nonce( $_POST['usc_reset_all_credits_nonce'], 'usc_reset_all_credits_action' ) ) {
            wp_die( __( 'Security check failed.', 'ultimate-store-credits' ) );
        }

        $amount = get_option( 'usc_yearly_credit_amount', 400 );
        USC_Cron::reset_all_user_credits( $amount, true );

        wp_safe_redirect( add_query_arg( 'usc_message', 'all_reset_done', admin_url( 'admin.php?page=ultimate-store-credits' ) ) );
        exit;
    }

    public function process_reset_single_user_credits() {
        if ( ! isset( $_POST['usc_reset_single_user_credits_nonce'] ) ||
             ! wp_verify_nonce( $_POST['usc_reset_single_user_credits_nonce'], 'usc_reset_single_user_credits_action' ) ) {
            wp_die( __( 'Security check failed.', 'ultimate-store-credits' ) );
        }

        $identifier = isset( $_POST['usc_user_identifier'] ) ? sanitize_text_field( $_POST['usc_user_identifier'] ) : '';
        if ( empty( $identifier ) ) {
            wp_safe_redirect( add_query_arg( 'usc_error', 'no_identifier', admin_url( 'admin.php?page=ultimate-store-credits' ) ) );
            exit;
        }

        $user = $this->get_user_by_id_or_email( $identifier );
        if ( ! $user ) {
            wp_safe_redirect( add_query_arg( 'usc_error', 'user_not_found', admin_url( 'admin.php?page=ultimate-store-credits' ) ) );
            exit;
        }

        $amount = get_option( 'usc_yearly_credit_amount', 400 );
        update_user_meta( $user->ID, 'usc_store_credits', $amount );

        wp_safe_redirect( add_query_arg(
            array(
                'usc_message' => 'single_reset_done',
                'usc_user'    => $user->ID,
            ),
            admin_url( 'admin.php?page=ultimate-store-credits' )
        ) );
        exit;
    }

    public function process_test_rollover() {
        if ( ! isset( $_POST['usc_test_rollover_nonce'] ) ||
             ! wp_verify_nonce( $_POST['usc_test_rollover_nonce'], 'usc_test_rollover_action' ) ) {
            wp_die( __( 'Security check failed.', 'ultimate-store-credits' ) );
        }

        $identifier = isset( $_POST['usc_test_user_identifier'] ) ? sanitize_text_field( $_POST['usc_test_user_identifier'] ) : '';
        if ( empty( $identifier ) ) {
            wp_safe_redirect( add_query_arg( 'usc_error', 'no_identifier_test', admin_url( 'admin.php?page=ultimate-store-credits' ) ) );
            exit;
        }

        $user = $this->get_user_by_id_or_email( $identifier );
        if ( ! $user ) {
            wp_safe_redirect( add_query_arg( 'usc_error', 'user_not_found_test', admin_url( 'admin.php?page=ultimate-store-credits' ) ) );
            exit;
        }

        // Simulate new balance
        $current_balance = (float) get_user_meta( $user->ID, 'usc_store_credits', true );
        $yearly_amount   = (float) get_option( 'usc_yearly_credit_amount', 400 );
        $allow_rollover  = get_option( 'usc_allow_rollover', 'no' );
        $rollover_max    = get_option( 'usc_rollover_max', '' );

        if ( 'yes' === $allow_rollover ) {
            $simulated_new_balance = $current_balance + $yearly_amount;
            if ( $rollover_max ) {
                $max = floatval( $rollover_max );
                if ( $max > 0 ) {
                    $simulated_new_balance = min( $simulated_new_balance, $max );
                }
            }
        } else {
            $simulated_new_balance = $yearly_amount;
        }

        // We do NOT update the user meta, just show an admin notice
        wp_safe_redirect( add_query_arg(
            array(
                'usc_message' => 'test_rollover_done',
                'usc_user'    => $user->ID,
                'usc_sim'     => $simulated_new_balance,
            ),
            admin_url( 'admin.php?page=ultimate-store-credits' )
        ) );
        exit;
    }

    private function get_user_by_id_or_email( $identifier ) {
        $user = null;
        if ( is_numeric( $identifier ) ) {
            $user = get_user_by( 'id', (int) $identifier );
        }
        if ( ! $user && is_email( $identifier ) ) {
            $user = get_user_by( 'email', $identifier );
        }
        return $user;
    }

    public function maybe_show_admin_notices() {
        $current_screen = get_current_screen();
        if ( ! $current_screen || false === strpos( $current_screen->id, 'ultimate-store-credits' ) ) {
            return;
        }

        if ( isset( $_GET['usc_message'] ) ) {
            switch ( $_GET['usc_message'] ) {
                case 'all_reset_done':
                    echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'All user credits have been reset.', 'ultimate-store-credits' ) . '</p></div>';
                    break;
                case 'single_reset_done':
                    $user_id = isset( $_GET['usc_user'] ) ? absint( $_GET['usc_user'] ) : 0;
                    if ( $user_id ) {
                        echo '<div class="notice notice-success is-dismissible"><p>' . sprintf(
                            esc_html__( 'Store credits were reset for user ID #%d.', 'ultimate-store-credits' ),
                            $user_id
                        ) . '</p></div>';
                    }
                    break;
                case 'settings_saved':
                    echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Settings saved successfully.', 'ultimate-store-credits' ) . '</p></div>';
                    break;
                case 'test_rollover_done':
                    $user_id = isset( $_GET['usc_user'] ) ? absint( $_GET['usc_user'] ) : 0;
                    $sim     = isset( $_GET['usc_sim'] ) ? floatval( $_GET['usc_sim'] ) : 0;
                    if ( $user_id ) {
                        echo '<div class="notice notice-info is-dismissible"><p>' . sprintf(
                            esc_html__( 'Simulated rollover for user ID #%d. New balance would be $%s.', 'ultimate-store-credits' ),
                            $user_id,
                            number_format_i18n( $sim, 2 )
                        ) . '</p></div>';
                    }
                    break;
            }
        }

        if ( isset( $_GET['usc_error'] ) ) {
            switch ( $_GET['usc_error'] ) {
                case 'no_identifier':
                case 'no_identifier_test':
                    echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__( 'No user identifier was provided.', 'ultimate-store-credits' ) . '</p></div>';
                    break;
                case 'user_not_found':
                case 'user_not_found_test':
                    echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__( 'User not found.', 'ultimate-store-credits' ) . '</p></div>';
                    break;
            }
        }
    }
}
