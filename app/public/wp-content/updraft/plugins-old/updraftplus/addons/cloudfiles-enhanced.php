<?php
// @codingStandardsIgnoreStart
/*
UpdraftPlus Addon: cloudfiles-enhanced:Rackspace Cloud Files, enhanced
Description: Adds enhanced capabilities for Rackspace Cloud Files users
Version: 1.8
RequiresPHP: 5.3.3
Shop: /shop/cloudfiles-enhanced/
Latest Change: 1.16.19
*/
// @codingStandardsIgnoreEnd

// Future possibility: sub-folders
if (!defined('UPDRAFTPLUS_DIR')) die('No direct access allowed');

// The new Rackspace SDK is PHP 5.3.3 or later
if (version_compare(phpversion(), '5.3.3', '<') || (defined('UPDRAFTPLUS_CLOUDFILES_USEOLDSDK') && UPDRAFTPLUS_CLOUDFILES_USEOLDSDK)) return;

use OpenCloud\Rackspace;

$updraftplus_addon_cloudfilesenhanced = new UpdraftPlus_Addon_CloudFilesEnhanced;// phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable -- Unused variable $updraftplus_addon_cloudfilesenhanced But it is used Globally in class-commands.php so ignoring

class UpdraftPlus_Addon_CloudFilesEnhanced {
	
	private $accounts;

	private $regions;
	
	/**
	 * Class constructor
	 */
	public function __construct() {
		add_action('updraftplus_settings_page_init', array($this, 'updraftplus_settings_page_init'));
		add_action('updraft_cloudfiles_newuser', array($this, 'newuser'));
		add_filter('updraft_cloudfiles_apikeysetting', array($this, 'apikeysettings'));
		add_filter('updraft_cloudfiles_template_properties', array($this, 'partial_template_properties'));
	}
	
	/**
	 * Called by the WP action updraftplus_settings_page_init
	 */
	public function updraftplus_settings_page_init() {
		
		$this->accounts = array(
			'us' => __('US (default)', 'updraftplus'),
			'uk' => __('UK', 'updraftplus')
		);
		
		$this->regions = array(
			'DFW' => __('Dallas (DFW) (default)', 'updraftplus'),
			'SYD' => __('Sydney (SYD)', 'updraftplus'),
			'ORD' => __('Chicago (ORD)', 'updraftplus'),
			'IAD' => __('Northern Virginia (IAD)', 'updraftplus'),
			'HKG' => __('Hong Kong (HKG)', 'updraftplus'),
			'LON' => __('London (LON)', 'updraftplus')
		);
		
		add_action('admin_footer', array($this, 'admin_footer'));
	}
	
	/**
	 * Compose partial template that deals with apikeysettings
	 *
	 * @param String $msg A filterable partial templates
	 * @return String the partial template, ready for substitutions to be carried out
	 */
	public function apikeysettings($msg) {
		ob_start();
		?>
		<a href="{{updraftplus_current_clean_url}}" target="_blank" id="dw{{instance_id}}" class="updraft_cloudfiles_newapiuser" data-instance_id="{{instance_id}}"><em>{{api_key_setting_premium_label}}</em></a>
		<?php
		$msg = ob_get_clean();
		return $msg;
	}

	/**
	 * This method is hooked to a filter and going to be accessed by any code within WordPress environment, so instead of sanitising each value in this method and/or using any other technique to prevent XSS attacks, just make sure each partial template has all variables escaped
	 *
	 * @return Array an associative array keyed by names that describe themselves as they are
	 */
	public function partial_template_properties() {
		return array(
			'api_key_setting_premium_label' => __('Create a new API user with access to only this container (rather than your whole account)', 'updraftplus'),
			'updraftplus_current_clean_url' => UpdraftPlus::get_current_clean_url(),
		);
	}
	
	/**
	 * Calls the Rackspace API to create a new API user
	 *
	 * @param  Array $use_settings - expected keys are: adminuser, adminapikey, newuser, container, newemail; also allowed are location, region
	 *
	 * @return Array - the contents depend upon the outcome. 'e' will be present (0|1) to indicate failure or success
	 */
	public function create_api_user($use_settings) {
		
		if (!isset($use_settings['adminuser'])) {
			return array('e' => 1, 'm' => __('You need to enter an admin username', 'updraftplus'));
		}
		if (empty($use_settings['adminapikey'])) {
			return array('e' => 1, 'm' => __('You need to enter an admin API key', 'updraftplus'));
		}
		if (!isset($use_settings['newuser'])) {
			return array('e' => 1, 'm' => __('You need to enter a new username', 'updraftplus'));
		}
		if (empty($use_settings['container'])) {
			return array('e' => 1, 'm' => __('You need to enter a container', 'updraftplus'));
		}
		// Here, 0 == catches both 0 and false
		if (empty($use_settings['newemail']) || 0 == strpos($use_settings['newemail'], '@')) {
			return array('e' => 1, 'm' => __('You need to enter a valid new email address', 'updraftplus'));
		}
		if (empty($use_settings['location'])) $use_settings['location'] = 'us';
		if (empty($use_settings['region'])) $use_settings['region'] = 'DFW';
		
		updraft_try_include_file('methods/cloudfiles.php', 'include_once');
		updraft_try_include_file('vendor/autoload.php', 'include_once');
		$method = new UpdraftPlus_BackupModule_cloudfiles;
		$useservercerts = !empty($use_settings['useservercerts']);
		$disableverify = !empty($use_settings['disableverify']);
		$auth_url = ('uk' == $use_settings['location']) ? Rackspace::UK_IDENTITY_ENDPOINT : Rackspace::US_IDENTITY_ENDPOINT;
		
		try {
			$storage = $method->get_openstack_service(
				array(
					'user' => $use_settings['adminuser'],
					'apikey' => $use_settings['adminapikey'],
					'authurl' => $auth_url,
					'region' => $use_settings['region']
				),
				$useservercerts,
				$disableverify
			);
		} catch (AuthenticationError $e) {
			global $updraftplus;
			$updraftplus->log('Cloud Files authentication failed ('.$e->getMessage().')');
			$updraftplus->log(__('Cloud Files authentication failed', 'updraftplus').' ('.$e->getMessage().')', 'error');
			return false;
		} catch (Exception $e) {
			return array('e' => 1, 'm' => __('Error:', 'updraftplus').' '.$e->getMessage());
		}
		
		// Create the container (if necessary)
		// Get the container
		try {
			$container_object = $storage->getContainer($use_settings['container']);
		} catch (Guzzle\Http\Exception\ClientErrorResponseException $e) {
			$container_object = $storage->createContainer($use_settings['container']);
		} catch (Exception $e) {
			return array('e' => 1, 'm' => __('Cloud Files authentication failed', 'updraftplus').' ('.get_class($e).', '.$e->getMessage().')');
		}
		
		if (!is_a($container_object, 'OpenCloud\ObjectStore\Resource\Container') && !is_a($container_object, 'Container')) {
			return array('e' => 1, 'm' => __('Cloud Files authentication failed', 'updraftplus').' ('.get_class($container_object).')');
		}
		
		// Create the new user
		$json = json_encode(
			array(
				'user' => array(
					'username' => $use_settings['newuser'],
					'email' => $use_settings['newemail'],
					'enabled' => true
				)
			)
		);
		
		$client = $method->get_client();
		
		try {
			$response = $client->post($auth_url.'users', array('Content-Type' => 'application/json', 'Accept' => 'application/json'), $json)->send()->json();
		} catch (Guzzle\Http\Exception\ClientErrorResponseException $e) {
			$response = $e->getResponse();
			$code = $response->getStatusCode();
			$reason = $response->getReasonPhrase();
			if (403 == $code) {
				return array('e' => 1, 'm' => __('Authorisation failed (check your credentials)', 'updraftplus'));
			} elseif (409 == $code && 'Conflict' == $reason) {
				return array('e' => 1, 'm' => __('Conflict: that user or email address already exists', 'updraftplus'));
			} else {
				return array('e' => 1, 'm' => sprintf(__('Cloud Files operation failed (%s)', 'updraftplus'), 5)." (".$e->getMessage().') ('.get_class($e).')');
			}
		} catch (Exception $e) {
			return array('e' => 1, 'm' => sprintf(__('Cloud Files operation failed (%s)', 'updraftplus'), 4).' ('.$e->getMessage().') ('.get_class($e).')');
		}
		
		if (empty($response['user']['id']) || empty($response['user']['OS-KSADM:password']) || empty($response['user']['username'])) {
			return array('e' => 1, 'm' => sprintf(__('Cloud Files operation failed (%s)', 'updraftplus'), 3));
		}
		
		$user = $response['user']['username'];
		$pass = $response['user']['OS-KSADM:password'];
		$id = $response['user']['id'];
		
		// Add the user to the container
		try {
			// The 'X-Container-Write' and 'X-Container-Read' headers always override the previous ACL configurations on the existing container. We need to also include the other users who already have access to this container to ensure we don't remove their access.
			$container_metadata = $container_object->getMetadata();
			$container_read_users = (null !== $container_metadata->getProperty('read') && '' != $container_metadata->getProperty('read')) ? $container_metadata->getProperty('read') . ',' . $user : $user;
			$container_write_users = (null !== $container_metadata->getProperty('write') && '' != $container_metadata->getProperty('write')) ? $container_metadata->getProperty('write') . ',' . $user : $user;
			$headers = array('X-Container-Write' => $container_write_users, 'X-Container-Read' => $container_read_users);
			$container_object->getClient()->post($container_object->getUrl(), $headers)->send();
		} catch (Exception $e) {
			return array('e' => 1, 'm' => sprintf(__('Cloud Files operation failed (%s)', 'updraftplus'), 1).' ('.$e->getMessage().') ('.get_class($e).')');
		}
		
		// Get an API key for the user
		try {
			$response = $container_object->getClient()->post($auth_url."users/$id/OS-KSADM/credentials/RAX-KSKEY:apiKeyCredentials/RAX-AUTH/reset", array())->send()->json();
			if (empty($response['RAX-KSKEY:apiKeyCredentials']['apiKey'])) {
				return array('e' => 1, 'm' => sprintf(__('Cloud Files operation failed (%s)', 'updraftplus'), 8));
			}
			$apikey = $response['RAX-KSKEY:apiKeyCredentials']['apiKey'];
		} catch (Exception $e) {
			return array('e' => 1, 'm' => sprintf(__('Cloud Files operation failed (%s)', 'updraftplus'), 7).' ('.$e->getMessage().') ('.get_class($e).')');
		}
		
		return array(
			'e' => 0,
			'u' => htmlspecialchars($user),
			'p' => htmlspecialchars($pass),
			'k' => htmlspecialchars($apikey),
			'a' => $auth_url = ('uk' == $use_settings['location']) ? 'https://lon.auth.api.rackspacecloud.com' : 'https://auth.api.rackspacecloud.com',
			'r' => $use_settings['region'],
			'c' => $use_settings['container'],
			'm' => htmlspecialchars(sprintf(__("Username: %s", 'updraftplus'), $user))."<br>".htmlspecialchars(sprintf(__("Password: %s", 'updraftplus'), $pass))."<br>".htmlspecialchars(sprintf(__("API Key: %s", 'updraftplus'), $apikey))
		);
	}
	
	/**
	 * Create a new user
	 *
	 * @uses self::create_api_user()
	 *
	 * @param Array $use_settings - user settings
	 */
	public function newuser($use_settings = array()) {
		$data = $this->create_api_user($use_settings);
		echo json_encode($data);
		die();
	}

	public function admin_footer() {
		$this->modal_css();
		$this->modal_html();
		$this->modal_script();
	}
	
	private function modal_css() {
		?>
		<style type="text/css">
			#updraft_cfnewapiuser_form label {
				float: left;
				clear:left;
				width: 200px;
			}
			#updraft_cfnewapiuser_form input[type="text"],
			#updraft_cfnewapiuser_form select {
				float: left;
				width: 230px;
			}
		</style>
		<?php
	}
	
	public function account_options() {
		return $this->accounts;
	}
	
	private function get_account_options() {
		
		$selaccount = 'us';
		foreach ($this->accounts as $acc => $desc) {
			?><option <?php if ($selaccount == $acc) echo 'selected="selected"'; ?> value="<?php echo esc_attr($acc); ?>"><?php echo esc_html($desc); ?></option><?php
		};
	}
	
	public function region_options() {
		return $this->regions;
	}
	
	private function get_region_options() {
		
		$selregion = 'DFW';
		foreach ($this->regions as $reg => $desc) {
			?>
			<option <?php if ($selregion == $reg) echo 'selected="selected"'; ?> value="<?php echo esc_attr($reg); ?>"><?php echo esc_html($desc); ?></option>
			<?php
		};
	}
	
	public function modal_html() {
		?>
		<div id="updraft-cfnewapiuser-modal" title="<?php esc_attr_e('Create new API user and container', 'updraftplus');?>" style="display:none;">
			<div id="updraft_cfnewapiuser_form">
				<p style="margin:1px; padding-top:0; clear: left; float: left;">
				<em><?php esc_html_e('Enter your Rackspace admin username/API key (so that Rackspace can authenticate your permission to create new users), and enter a new (unique) username and email address for the new user and a container name.', 'updraftplus');?></em>
				</p>
				<div id="updraft-cfnewapiuser-results" style="clear: left; float: left;">
					<p></p>
				</div>

				<p style="margin-top:3px; padding-top:0; clear: left; float: left;">

					<label for="updraft_cfnewapiuser_accountlocation"><?php esc_html_e('US or UK Rackspace Account', 'updraftplus');?></label>
					<select title="<?php esc_attr_e('Accounts created at rackspacecloud.com are US accounts; accounts created at rackspace.co.uk are UK accounts.', 'updraftplus');?>" id="updraft_cfnewapiuser_accountlocation">
						<?php $this->get_account_options();?>
					</select>

					<label for="updraft_cfnewapiuser_adminusername"><?php esc_html_e('Admin Username', 'updraftplus');?></label>
					<input type="text" id="updraft_cfnewapiuser_adminusername" value="">
					
					<label for="updraft_cfnewapiuser_adminapikey"><?php esc_html_e('Admin API Key', 'updraftplus');?></label>
					<input type="text" id="updraft_cfnewapiuser_adminapikey" value="">
					
					<label for="updraft_cfnewapiuser_newuser"><?php esc_html_e("New User's Username", 'updraftplus');?></label>
					<input type="text" id="updraft_cfnewapiuser_newuser" value="">
					
					<label for="updraft_cfnewapiuser_newemail"><?php esc_html_e("New User's Email Address", 'updraftplus');?></label>
					<input type="text" id="updraft_cfnewapiuser_newemail" value="">

					<label for="updraft_cfnewapiuser_region"><?php esc_html_e('Cloud Files Storage Region', 'updraftplus');?>:</label>
					<select id="updraft_cfnewapiuser_region">
						<?php $this->get_region_options();?>
					</select>
					<label for="updraft_cfnewapiuser_container"><?php esc_html_e("Cloud Files Container", 'updraftplus');?></label> <input type="text" id="updraft_cfnewapiuser_container" value="">
					
				</p>
				<fieldset>
					<input type="hidden" name="nonce" value="<?php echo esc_attr(wp_create_nonce('updraftplus-credentialtest-nonce')); ?>">
					<input type="hidden" name="action" value="updraft_ajax">
					<input type="hidden" name="subaction" value="cloudfiles_newuser">
					<input type="hidden" id="updraft_cfnewapiuser_instance_id" name="updraft_cfnewapiuser_instance_id" value="" />
				</fieldset>
			</div>
		</div>
		<?php
	}
	
	private function modal_script() {
		?>
		<script>
		jQuery(function($) {
		
			function set_allowable_regions() {
				var account_location = $('#updraft_cfnewapiuser_accountlocation').val();
				console.log(account_location);
				if ('uk' === account_location) {
					$('#updraft_cfnewapiuser_region option[value="LON"]').prop('disabled', false);
				} else {
					var region_selected = $('#updraft_cfnewapiuser_region').val();
					if ('LON' == region_selected) {
						$('#updraft_cfnewapiuser_region').val('DFW');
					}
					$('#updraft_cfnewapiuser_region option[value="LON"]').prop('disabled', true);
				}
			}
		
			$('#updraft-navtab-settings-content').on('click', '.updraft_cloudfiles_newapiuser', function(e) {
				e.preventDefault();
				jQuery('#updraft_cfnewapiuser_instance_id').val(jQuery(this).data('instance_id'));
				jQuery('#updraft-cfnewapiuser-modal').dialog('open');
				set_allowable_regions();
			});
			
			jQuery('#updraft_cfnewapiuser_accountlocation').on('change', function() {
				set_allowable_regions();
			});

			var updraft_cfnewapiuser_modal_buttons = {};
			
			updraft_cfnewapiuser_modal_buttons[updraftlion.cancel] = function() { jQuery(this).dialog("close"); };
			updraft_cfnewapiuser_modal_buttons[updraftlion.createbutton] = function() {
				jQuery('#updraft-cfnewapiuser-results').html('<p style="color:green">'+updraftlion.trying+'</p>');
				var data = {
					subsubaction: 'updraft_cloudfiles_newuser',
					adminuser: jQuery('#updraft_cfnewapiuser_adminusername').val(),
					adminapikey: jQuery('#updraft_cfnewapiuser_adminapikey').val(),
					newuser: jQuery('#updraft_cfnewapiuser_newuser').val(),
					newemail: jQuery('#updraft_cfnewapiuser_newemail').val(),
					container: jQuery('#updraft_cfnewapiuser_container').val(),
					location: jQuery('#updraft_cfnewapiuser_accountlocation').val(),
					region: jQuery('#updraft_cfnewapiuser_region').val(),
					useservercerts: jQuery('#updraft_ssl_useservercerts').val(),
					disableverify: jQuery('#updraft_ssl_disableverify').val()
				};

				updraft_send_command('doaction', data, function(resp, status, response) {
					if (resp.e == 1) {
						jQuery('#updraft-cfnewapiuser-results').html('<p style="color:red;">'+resp.m+'</p>');
					} else if (resp.e == 0) {
						jQuery('#updraft-cfnewapiuser-results').html('<p style="color:green;">'+resp.m+'</p>');
						var instance_id = jQuery('#updraft_cfnewapiuser_instance_id').val();
						jQuery('#updraft_cloudfiles_user_'+instance_id).val(resp.u);
						jQuery('#updraft_cloudfiles_apikey_'+instance_id).val(resp.k);
						jQuery('#updraft_cloudfiles_authurl_'+instance_id).val(resp.a);
						jQuery('#updraft_cloudfiles_region_'+instance_id).val(resp.r);
						jQuery('#updraft_cloudfiles_path_'+instance_id).val(resp.c);
						jQuery('#dw'+instance_id).after('<p><strong>'+updraftlion.newuserpass+'</strong> '+resp.p+'<p>');
						jQuery('#updraft-cfnewapiuser-modal').dialog('close');
					}
				}, { error_callback: function(response, status, error_code, resp) {
						if ('undefined' !== typeof resp  && resp.hasOwnProperty('fatal_error')) {
							jQuery('#updraft-cfnewapiuser-results').html('<p style="color:red;">'+resp.fatal_error_message+'</p>');
							console.error(resp.fatal_error_message);
						} else {
							console.log("updraft_send_command: error: "+status+" ("+error_code+")");
							jQuery('#updraft-cfnewapiuser-results').html('<p style="color:red;">'+updraftlion.servererrorcode+'</p>');
							alert(updraftlion.unexpectedresponse+' '+response);
							console.log(response);
						}
					}
			 });
			};
			jQuery("#updraft-cfnewapiuser-modal").dialog({
				autoOpen: false, height: 465, width: 555, modal: true,
				buttons: updraft_cfnewapiuser_modal_buttons
			});
			
		});
		</script>
		<?php
	}
}
