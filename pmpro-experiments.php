<?php
/*
Plugin Name: PMPro Experiments
Plugin URI: http://www.paidmembershipspro.com/wp/pmpro-experiments/
Description: Run Experiments and A/B tests with PMPro
Version: .1
Author: Stranger Studios
Author URI: http://www.strangerstudios.com
*/

/*
	New Experiment
	* Name 
	* Entrance Page
	* Redirect URLs

	Report
	* Visits to starting page.
	* Visits to each redirect URL
	* Checkouts from each redirect URL
	* Revenue from each redirect URL
*/

/*
	We'll eventually put these into a settings page, possibly using CPTs, but for now
	we define them in a global var.
*/
global $pmpro_experiments;
$pmpro_experiments = array(
	1 => array(
		'name' => 'Frontpage_1',	//no spaces or special characters please
		'entrance' => 'frontpage',	//frontpage or slug of page that starts the experiment
		'urls' => array(			//urls to redirect to
			'197', '297'
		),
		'goals' => array(
			'membership-checkout',
			'membership-confirmation',
		),
		'redirect' => false,		//set to false if you don't want to redirect and just reference the URL/value
		'status' => 'active',		//set to inactive to disable
		'copy' => 'Pricing_1',//if experiment 'Frontpage_1' has a value, copy it
	),
	2 => array(
		'name' => 'Pricing_1',	//no spaces or special characters please
		'entrance' => 'pricing',
		'urls' => array(
			'197', '297'
		),
		'goals' => array(
			'membership-checkout',
			'membership-confirmation',
		),
		'redirect' => false,
		'status' => 'active',
		'copy' => 'Frontpage_1',//if experiment 'Frontpage_1' has a value, copy it
	)
);

/*
	Just returning global for now, but will eventually pull these from options/etc
*/
function pmproex_getExperiments()
{
	global $pmpro_experiments;
	return $pmpro_experiments;
}

function pmproex_getExperiment($name)
{
	$experiments = pmproex_getExperiments();	
	
	if(!empty($experiments))
	{
		foreach($experiments as $experiment)
			if($experiment['name'] == $name)
				return $experiment;
	}
	
	return false;
}

function pmproex_getExperimentDescription( $pmpro_experiments_name )
{
	$pmpro_experiments_description = "";
	
	$experiments = pmproex_getExperiments();
	foreach($experiments as $experiment)
	{
		if($experiment['name'] == $pmpro_experiments_name && !empty($experiment['description']))
		{
			$pmpro_experiments_description = $experiment['description'];
		}
	}
	return $pmpro_experiments_description;
}

/*
	Track views on entrances and URLs
*/
function pmproex_track($experiment, $url, $goal = NULL)
{
	$stats = get_option('pmpro_experiments_stats', array());	

	//create experiment element if not there
	if(empty($stats[$experiment]))
	{
		$stats[$experiment] = array('entrances'=>0);
	}
	
	if(empty($goal))
	{
		//tracking an entrance		
		$stats[$experiment]['entrances']++;
		if(empty($stats[$experiment][$url]))
			$stats[$experiment][$url] = array('entrances'=>0);
		$stats[$experiment][$url]['entrances']++;
	}
	else
	{
		//tracking a goal
		if(empty($stats[$experiment][$url]))
			$stats[$experiment][$url] = array('entrances'=>0, $goal=>0);
		elseif(empty($stats[$experiment][$url][$goal]))
			$stats[$experiment][$url][$goal] = 0;
		$stats[$experiment][$url][$goal]++;
	}
	
	//delete_option('pmpro_experiments_stats');		
	update_option('pmpro_experiments_stats', $stats, 'no');
}

/*
	When visiting an entrance page, redirect to one of the redirect URLs randomly
*/
function pmproex_template_redirect()
{
	$experiments = pmproex_getExperiments();

	//no experiments?
	if(empty($experiments))
		return;

	//see we are entering any experiment
	foreach($experiments as $experiment)
	{
		if(!isset($experiment['status']) || $experiment['status'] == 'active')
		{			
			if($experiment['entrance'] == 'frontpage' && is_front_page() || is_page($experiment['entrance']))
			{				
				//check session cookie
				if(!empty($_COOKIE['pmpro_experiment_' . $experiment['name']]))					
				{
					if(!isset($experiment['redirect']) || $experiment['redirect'] !== false)
					{
						wp_redirect($_COOKIE['pmpro_experiment_' . $experiment['name']]);
						exit;
					}
				}
				elseif(!empty($experiment['copy']) && !empty($_COOKIE['pmpro_experiment_' . $experiment['copy']]))
				{
					//copy url
					$url = $_COOKIE['pmpro_experiment_' . $experiment['copy']];
					
					//track views
					pmproex_track($experiment['name'], $url);
					
					//save cookie					
					setcookie('pmpro_experiment_' . $experiment['name'], $url, 0, COOKIEPATH, COOKIE_DOMAIN, false);
	
					//set cookie for now if we don't redirect
					$_COOKIE['pmpro_experiment_' . $experiment['name']] = $url;
	
					//redirect					
					if(!isset($experiment['redirect']) || $experiment['redirect'] !== false)
					{
						wp_redirect($url);
						exit;
					}
				}
				else
				{
					//need to choose a URL at random
					$rand = rand(0, count($experiment['urls']) - 1);
					$url = $experiment['urls'][$rand];
	
					//track views
					pmproex_track($experiment['name'], $url);
					
					//save cookie
					setcookie('pmpro_experiment_' . $experiment['name'], $url, 0, COOKIEPATH, COOKIE_DOMAIN, false);
	
					//set cookie for now if we don't redirect
					$_COOKIE['pmpro_experiment_' . $experiment['name']] = $url;
	
					//redirect					
					if(!isset($experiment['redirect']) || $experiment['redirect'] !== false)
					{
						wp_redirect($url);
						exit;
					}
				}
	
			}
			else
			{
				//check if any goals were met
				if(!empty($experiment['goals']))
				{
					foreach($experiment['goals'] as $goal)
					{
						if(is_page($goal))
						{
							if(empty($_COOKIE['pmpro_experiment_goal_' . $experiment['name'] . "_" . $goal]))
							{
								//get url
								if(!empty($_COOKIE['pmpro_experiment_' . $experiment['name']]))
								{
									$url = $_COOKIE['pmpro_experiment_' . $experiment['name']];
								
									//save cookie that we've hit this goal
									$_COOKIE['pmpro_experiment_goal_' . $experiment['name'] . "_" . $goal] = 1;
									setcookie('pmpro_experiment_goal_' . $experiment['name'] . "_" . $goal, 1, 0, COOKIEPATH, COOKIE_DOMAIN, false);
										
									//track it
									pmproex_track($experiment['name'], $url, $goal);									
								}
							}
							break;
						}
					}
				}				
			}
		}
	}
}
add_action('template_redirect', 'pmproex_template_redirect');

/*
	Save experiment names and urls to order notes.
*/
function pmproex_pmpro_checkout_order($order)
{
	if(!empty($_COOKIE))
	{
		foreach($_COOKIE as $name => $value)
		{
			if(strpos($name, "pmpro_experiment_") !== false)
			{
				$order->notes .= "Experiment (" . str_replace("pmpro_experiment_", "", $name) . "): " . $value . "\n";
			}
		}
	}

	return $order;
}
add_filter('pmpro_checkout_order', 'pmproex_pmpro_checkout_order');
add_filter('pmpro_checkout_order_free', 'pmproex_pmpro_checkout_order');

/*
	Show orders notes in admin confirmation emails.
*/
function pmproex_pmpro_email_filter($email)
{
	global $wpdb;
 	
	//only update admin confirmation emails
	if(strpos($email->template, "checkout") !== false && strpos($email->template, "_admin") !== false)
	{
		//get the user_id from the email
		$order_id = $email->data['invoice_id'];
		if(!empty($order_id))
		{
			$order = new MemberOrder($order_id);
						
			//add to bottom of email
			if(!empty($order->notes))
			{
				$email->body .= "<p>Order Notes</p><hr /><p>" . $order->notes . "</p>";
			}
		}
	}
		
	return $email;
}
add_filter("pmpro_email_filter", "pmproex_pmpro_email_filter", 10, 2);

/*
function init_test_a()
{
	if(!empty($_REQUEST['stats']))
	{
		$stats = get_option('pmpro_experiments_stats', array());
		d($stats);
		exit;
	}
}
add_action('init', 'init_test_a');
*/

global $pmpro_reports;
$pmpro_reports['pmproex'] = __('PMPro Experiments', 'pmpro-experiments');

function pmpro_report_pmproex_widget()
{	
	$stats = get_option('pmpro_experiments_stats', array());		
}

function pmpro_report_pmproex_page()
{
	global $wpdb;
	
	$experiments = pmproex_getExperiments();
	
	//resetting?
	if(!empty($_REQUEST['resetall']))
	{
		if(wp_verify_nonce($_REQUEST['resetall'], 'pmpro-experiments-resetall'))
			update_option('pmpro_experiments_stats', array(), 'no');
	}
?>
<h2>
	<?php _e('PMPro Experiments', 'pmpro-experiments');?>
</h2>
<?php
	$stats = get_option('pmpro_experiments_stats', array());	
	if(!empty($stats)) 
	{
		foreach($stats as $experiment_name => $stat)
		{
			$experiment = pmproex_getExperiment($experiment_name);			
			?>
				<hr />
				<h3><?php echo $experiment['name'];?></h3>
				<p><?php if(!empty($experiment['description'])) echo $experiment['description']; ?></p>
				<table class="wp-list-table widefat striped">
				<thead>
					<tr>
						<th>URL</th>
						<th>Entrances</th>
						<th>Orders</th>
						<th>%</th>
						<?php
							if(!empty($experiment['goals']))
							{
								foreach($experiment['goals'] as $goal)
								{
								?>
								<th><?php echo $goal;?></th>
								<th>%</th>
								<?php
								}
							}
						?>						
						<th>Revenue</th>
					</tr>
				</thead>
				<tbody>
					<?php foreach($stat as $url => $entrances) { ?>
						<?php if($url == "entrances") continue; ?>
						<tr>
							<td><?php echo $url;?></td>
							<td>
								<?php echo $entrances['entrances']; ?>
							</td>
							<td>
								<?php
									$conversions = intval($wpdb->get_var("SELECT COUNT(*) FROM $wpdb->pmpro_membership_orders WHERE status NOT IN('refunded', 'review', 'token', 'error') AND notes LIKE '%Experiment (" . $experiment['name'] . "): " . $url . "%'"));
									echo $conversions;
								?>
							</td>
							<td>
								<?php
									echo round((intval($conversions)/intval($entrances['entrances']))*100, 2) . "%";
								?>
							</td>
							<?php
								if(!empty($experiment['goals']))
								{
									foreach($experiment['goals'] as $goal)
									{
									?>
									<th>
										<?php 
											if(!empty($entrances[$goal]))
												echo $entrances[$goal];
										?>
									</th>
									<th>
										<?php 
											if(!empty($entrances[$goal]))
												echo round((intval($entrances[$goal])/intval($entrances['entrances']))*100, 2) . "%";
										?>
									</th>
									<?php
									}
								}
							?>
							<td>
								<?php
									$revenue = $wpdb->get_var("SELECT SUM(total) FROM $wpdb->pmpro_membership_orders WHERE status NOT IN('refunded', 'review', 'token', 'error') AND notes LIKE '%Experiment (" . $experiment['name'] . "): " . $url . "%'");
									echo pmpro_formatPrice($revenue);
								?>
							</td>
						</tr>
					<?php } ?>
				</tbody>
				</table>
			<?php		
		}
	}
?>

<hr />

<p>Need to reset all stats? Click here to <a href="admin.php?page=pmpro-reports&report=pmproex&resetall=<?php echo wp_create_nonce("pmpro-experiments-resetall");?>">reset all experiment stats</a>.</p>

<?php
}