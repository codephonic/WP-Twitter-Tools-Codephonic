<?php 

class AKTT_Account {
	var $id = null; // This is the Social Service's user ID for the specified account
	var $social_acct = null;
	static $settings = array();
	
	public static function init() {
		// Set our default configs
		AKTT_Account::set_default_settings();
	}
	
	public static function set_default_settings() {
		// Set default configs
		$settings = array(
			'enabled' => array( // author to assign to new posts
				'label' => __('Enabled', 'twitter-tools'),
				'label_first' => false,
				'value' => 0,
				'type' 	=> 'int',
			),
			'create_posts' => array( // author to assign to new posts
				'label' => __('Create posts for each tweet?', 'twitter-tools'),
				'label_first' => false,
				'value' => 0,
				'type' 	=> 'int',
			),
			'post_author' => array( // author to assign to new posts
				'label' => __('Post Author', 'twitter-tools'),
				'label_first' => true,
				'value' => 0,
				'type' 	=> 'int',
			),
			'post_category' => array( // cats to add to posts created from this acct
				'label' => __('Post Category', 'twitter-tools'),
				'label_first' => true,
				'value' => 0,
				'type' 	=> 'is_cat',
			),
			'post_tags' => array( // tags to add to posts created from this acct
				'label' => __('Post Tags', 'twitter-tools'),
				'label_first' => true,
				'value' => '',
				'type' 	=> 'tags',
			),
			'exclude_reply_tweets' => array( // Exclude tweets that are a reply from creating their own blog posts?
				'label' => __('Exclude reply tweets from post creation', 'twitter-tools'),
				'label_first' => false,
				'value' => 1,
				'type' 	=> 'int',
			),
			'exclude_retweets' => array( // Exclude RTs from creating their own blog posts?
				'label' => __('Exclude re-tweets from post creation', 'twitter-tools'),
				'label_first' => false,
				'value' => 1,
				'type' 	=> 'int',
			),
			'blog_post_title' => array( // Structure of the blog post Title
				'label' => __('Blog Post Title Prefix', 'twitter-tools'),
				'label_first' => true,
				'value' => '',
				'type' 	=> 'no_html',
			),
		);
		AKTT_Account::$settings = apply_filters('aktt_account_default_settings', $settings);
	}
	
	/**
	 * Safe constructor.  
	 *
	 * @param object $acct Social Twitter Account (stdObj)
	 * @return AKTT_Account | false
	 */
	public static function load($acct = null) {
		// Make sure we have the appropriate classes, etc. for the AKTT_Account object
		if (
			is_null($acct) ||
			!is_object($acct) || // Ensure we have an account object
			!is_a($acct, 'Social_Service_Twitter_Account') // Ensure we have a Social_Twitter object
			) {
			return false;
		}
		
		// We passed the gauntlet, return a new object
		return new AKTT_Account($acct);
	}
	
	function __construct($acct = null) {
		// Set our ID
		$this->id = $acct->id();
		
		// Set the account (Social_Service_Twitter_Account)
		$this->social_acct = $acct;
		
		// For convenience, set a reference to the service which has all the account methods
		$this->service = &Social::instance()->service('twitter');
		
	}
	
	
	/**
	 * Get an option from the DB, and fall back to the default setting
	 *
	 * @param string $key 
	 * @return mixed
	 */
	function option($key) {
		$option = get_option('aktt_v3_accounts');
		if (
			!empty($option) 
			&& is_array($option) 
			&& isset($option[$this->id])
			&& isset($option[$this->id]['settings'])
			&& isset($option[$this->id]['settings'][$key])
			) {
			$val = $option[$this->id]['settings'][$key];
		}
		else {
		// Get a default
			$val = isset(AKTT_Account::$settings[$key]) ? AKTT_Account::$settings[$key]['value'] : null;
		}
		return apply_filters('aktt_account_option', $val, $key);
	}
	       

	function twitter_request($account, $api, array $args = array(), $method = 'GET') {
		if (!is_object($account)) {
			$account = $this->account($account);
		}
		if ($account !== false) {
  $config['key'] = get_option('tdf_consumer_key');
  $config['secret'] = get_option('tdf_consumer_secret');
  $config['token'] = get_option('tdf_access_token');
  $config['token_secret'] = get_option('tdf_access_token_secret');
  $config['screenname'] = get_option('tdf_user_timeline');
  $config['cache_expire'] = intval(get_option('tdf_cache_expire'));
  if ($config['cache_expire'] < 1) $config['cache_expire'] = 3600;
  $config['directory'] = plugin_dir_path(__FILE__);

  $obj = new StormTwitter($config);
  $res = $obj->searchTweets(20, $args['q'], array('include_entities' => 1, 'trim_user' => 0));

  print_r($res[0]);
  $body = array();
  $body['result'] = 'success';
  $body['response'] = $res;

  $request = array();
  $request['body'] = json_encode($body);
  

			if (!is_wp_error($request)) {
				$request['body'] = apply_filters('social_response_body', $request['body'], 'twitter');
				if (is_string($request['body'])) {
					// slashes are normalized (always added) by WordPress
					$request['body'] = json_decode(stripslashes_deep($request['body']));
				}
				return Social_Response::factory($this, $request, $account);
			}
			else {
				Social::log('Service::request() error: '.$request->get_error_message());
			}
		}
		return false;
	}



	function search_for_tweets($search_term) {
print_r('<br />----------------=================Results - Search===========-----------------<br />');
		$response = $this->twitter_request($this->social_acct, 'search/tweets', array(
                        'q' => $search_term,
			'count' => apply_filters('aktt_account_api_download_count', 20), // default to twitter's default 
			'include_entities' => 1 // include explicit hashtags and mentions
		));

//print_r($response);

		if ($response !== false) {
			$content = $response->body();
			if ($content->result == 'success') {
				return $content->response;
			}
		}
		return false;
	}


	function download_tweets() {
print_r('<br />----------------=================Results - User===========-----------------<br />');
		// Use Social to download tweets for this account
		$response = $this->service->request($this->social_acct, 'statuses/user_timeline', array(
			'count' => apply_filters('aktt_account_api_download_count', 20), // default to twitter's default 
			'include_entities' => 1, // include explicit hashtags and mentions
			'include_rts' => 1, // include retweets
		));

//print_r($response);

		if ($response !== false) {
			$content = $response->body();
			if ($content->result == 'success') {
				return $content->response;
			}
		}
		return false;
	}
	
	
	/**
	 * Saves the tweets passed in.
	 *
	 * @param array $tweets - safe tweets (do error checking before passing to this function)
	 * @return int - number of tweets saved
	 */
	function save_tweets($tweets) {
		global $wpdb;
// strip out any tweets we already have
		$tweet_guids = array();
		foreach ($tweets as $tweet) {
			$tweet_guids[] = AKTT_Tweet::guid_from_twid($tweet->id);
		}

		$existing_guids = $wpdb->get_col("
			SELECT guid
			FROM $wpdb->posts
			WHERE guid IN ('".implode("','", $tweet_guids)."')
			AND post_type = '".AKTT::$post_type."'
		");
  print_r("Check 1 <br />");
		
		// Set the args for any blog posts created
		$post_tweet_args = array(
			'post_author' => $this->option('post_author'),
			'post_category' => $this->option('post_category'),
			'post_tags' => $this->option('post_tags'),
			'title_prefix' => $this->option('blog_post_title'),
		);
		
  print_r("Check 2 <br />");
// Save new tweets
		foreach ($tweets as $tweet) {
			if (in_array(AKTT_Tweet::guid_from_twid($tweet->id), $existing_guids)) {
				continue;
			}

			// Start up a tweet object
    print_r("Check 2.1 <br />");
			$t = new AKTT_Tweet($tweet);
    print_r("Check 2.2 <br />");

			if (!($result = $t->add())) {
				AKTT::log('There was an error saving a tweet. Tweet ID: '.$t->id);
				continue;
			}
    print_r("Check 2.3 <br />");
  print_r($result);
  print_r('<br />');
  print_r($t->is_reply() == 1);
  print_r('<br />');
  print_r($t->is_retweet() == 1);
  print_r('<br />');
  print_r($t->tweet_post_exists() == 1);
  print_r('<br />');
  print_r($t->was_broadcast() == 1);
// Now conditionially create the associated blog post
			if (
				// If we are set to create blog posts
				$this->option('create_posts') == 1
				
				// AND NOT we aren't supposed to do reply tweets and this is a reply
				&& !($this->option('exclude_reply_tweets') && $t->is_reply())
				
				// AND NOT we aren't supposed to do re-tweets and this is a RT
				&& !($this->option('exclude_retweets') && $t->is_retweet())
				
				// AND this tweet hasn't created a post yet
				&& !$t->tweet_post_exists()
				
				// AND the tweet didn't come from a Social broadcast (ie, was originally a blog post)
				&& !$t->was_broadcast()
				){
				AKTT::log('Creating a blog post for tweet ID: '.$t->id);
  print_r("Check 3 <br />");
  print_r($post_tweet_args);
				$t->create_blog_post($post_tweet_args);
  print_r("Check 4 <br />");
			}
  print_r("Check 5 <br />");
		}
	}
	
}
add_action('init', array('AKTT_Account', 'init'));
