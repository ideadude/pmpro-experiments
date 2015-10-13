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
/*
global $pmpro_experiments;
$pmpro_experiments = array(
	1 => array(
		'name' => 'Experiment_1',	//no spaces or special characters please
		'entrance' => 'frontpage',
		'urls' => array(
			'test1', 'test2'
		)
	)
);
*/

/*
	Just returning global for now, but will eventually pull these from options/etc
*/
function pmproex_getExperiments()
{
	global $pmpro_experiments;
	return $pmpro_experiments;
}

function pmproex_getExperimentDescription( $pmpro_experiments_name )
{
	$experiments = pmproex_getExperiments();
	foreach($experiments as $experiment)
	{
		if($experiment['name'] == $pmpro_experiments_name)
		{
			$pmpro_experiments_description = $experiment['description'];
		}
	}
	return $pmpro_experiments_description;
}

/*
	Track views on entrances and URLs
*/
function pmproex_track($experiment, $url)
{
	$stats = get_option('pmpro_experiments_stats', array());	
	
	if(empty($stats[$experiment]))
	{
		$stats[$experiment] = array('entrances'=>1, $url=>1);
	}
	else
	{
		$stats[$experiment]['entrances']++;
		if(empty($stats[$experiment][$url]))
			$stats[$experiment][$url] = 0;
		$stats[$experiment][$url]++;
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
		if($experiment['status'] == 'active')
		{
			if($experiment['entrance'] == 'frontpage' && is_front_page() || is_page($experiment['entrance']))
			{
				//check session cookie
				if(!empty($_COOKIE['pmpro_experiment_' . $experiment['name']]))
				{
					wp_redirect($_COOKIE['pmpro_experiment_' . $experiment['name']]);
					exit;
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
	
					//redirect
					wp_redirect($url);
					exit;
				}
	
			}
			else
			{
				//maybe redirect from one redirect URL to another if the cookie is set here
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

global $pmpro_reports;
$pmpro_reports['pmproex'] = __('PMPro Experiments', 'pmpro-experiments');

function pmpro_report_pmproex_widget()
{	
	$stats = get_option('pmpro_experiments_stats', array());		
}

function pmpro_report_pmproex_page()
{
	global $wpdb;
?>
<h2>
	<?php _e('PMPro Experiments', 'pmpro-experiments');?>
</h2>
<?php
	$stats = get_option('pmpro_experiments_stats', array());
	//var_dump($stats);
	foreach($stats as $experiment => $stat)
	{	
					
		?>
			<hr />
			<h3><?php echo $experiment;?></h3>
			<p><?php echo pmproex_getExperimentDescription($experiment); ?></p>
			<table class="wp-list-table widefat striped">
			<thead>
				<tr>
					<th>URL</th>
					<th>Entrances</th>
					<th>Conversions</th>
					<th>%</th>
					<th>Revenue</th>
				</tr>
			</thead>
			<tbody>
				<?php foreach($stat as $url => $entrances) { ?>
					<?php if($url == "entrances") continue; ?>
					<tr>
						<td><?php echo $url;?></td>
						<td><?php echo $entrances;?></td>
						<td>
							<?php
								$conversions = intval($wpdb->get_var("SELECT COUNT(*) FROM $wpdb->pmpro_membership_orders WHERE status NOT IN('refunded', 'review', 'token', 'error') AND notes LIKE '%Experiment (" . $experiment . "): " . $url . "%'"));
								echo $conversions;
							?>
						</td>
						<td>
							<?php
								echo round((intval($conversions)/intval($entrances))*100, 2) . "%";
							?>
						</td>
						<td>
							<?php
								$revenue = $wpdb->get_var("SELECT SUM(total) FROM $wpdb->pmpro_membership_orders WHERE status NOT IN('refunded', 'review', 'token', 'error') AND notes LIKE '%Experiment (" . $experiment . "): " . $url . "%'");
								echo pmpro_formatPrice($revenue);
							?>
						</td>
					</tr>
				<?php } ?>
			</tbody>
			</table>
		<?php		
	}
?>
<hr />
<?php
}