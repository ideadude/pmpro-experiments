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
		'name' => 'Experiment_1',	//no spaces or special characters please
		'entrance' => 'frontpage',
		'urls' => array(
			'test1', 'test2'
		)
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
	
	delete_option('pmpro_experiments_stats');
	add_option('pmpro_experiments_stats', $stats, NULL, 'no');
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
	d($_COOKIE);

	foreach($_COOKIE as $name => $value)
	{
		if(strpos($name, "pmpro_experiment_") !== false)
		{
			d($name);
			d($value);
		}
	}
}
//add_action('init', 'init_test_a');