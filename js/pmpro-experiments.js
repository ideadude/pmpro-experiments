/*
	Function to get a cookie
*/
function pmproex_getCookie(cname) {
    var name = cname + "=";
    var ca = document.cookie.split(';');
    for(var i=0; i<ca.length; i++) {
        var c = ca[i];
        while (c.charAt(0)==' ') c = c.substring(1);
        if (c.indexOf(name) == 0) return c.substring(name.length,c.length);
    }
    return "";
}

/*
	Function to track via AJAX
*/
function pmpropex_track(experiment, url, goal) {
	jQuery.ajax({
		url: pmproex.ajaxurl,
		type:'GET',
		timeout: pmproex.timeout,
		dataType: 'text',
		data: "action=pmproex_track&experiment=" + experiment + "&url=" + url + "&goal=" + goal,
		error: function(xml){					
			//quiet error			
			},
		success: function(responseHTML)
		{
			//quiet success
		}
	});
}

/*
	Experiment or Goal?
*/
if(typeof pmproex.goal !== 'undefined' && pmproex.goal.length) {
	//Goal. Check for a cookie and track.
	var pmproex_cookie = pmproex_getCookie('pmpro_experiment_goal' + pmproex.goal);		
	if(!pmproex_cookie) {
		document.cookie = 'pmpro_experiment_goal_' + pmproex.goal + '=1';
		pmpropex_track(pmproex.experiment, pmproex_cookie, '1');
	}
} else if(typeof pmproex !== 'undefined') {	
	//Entrance. Check for a cookie, track, and redirect.
	var pmproex_cookie = pmproex_getCookie('pmpro_experiment_' + pmproex.experiment.name);
	if(pmproex_cookie.length) {
		//cookie found, track and redirect
		pmpropex_track(pmproex.experiment.name, pmproex_cookie, false);	
		window.location.href = decodeURIComponent(pmproex_cookie);
	} else {
		//no cookie, pick a url at random, set cookie, track and redirect
		pmproex_cookie = pmproex.experiment.urls[Math.floor(Math.random()*pmproex.experiment.urls.length)];	
		document.cookie = 'pmpro_experiment_' + pmproex.experiment.name + '=' + pmproex_cookie;
		pmpropex_track(pmproex.experiment.name, pmproex_cookie, false);
		window.location.href = decodeURIComponent(pmproex_cookie);
	}
}
