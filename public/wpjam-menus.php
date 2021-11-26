<?php
class WPJAM_Basic_Menu{
	public static function load_thumbnail_page($plugin_page){
		self::set_summary($plugin_page);

		WPJAM_Thumbnail_Setting::register_option();

		add_action('admin_head', ['WPJAM_Thumbnail_Setting', 'on_admin_head']);
	}

	public static function load_posts_page($plugin_page){
		self::set_summary($plugin_page);

		wpjam_register_plugin_page_tab('posts', [
			'title'			=> '文章列表',
			'function'		=> 'option',
			'option_name'	=> 'wpjam-basic',
			'site_default'	=> true,
			'order'			=> 20,
			'summary'		=> '文章设置优化，增强后台文章列表页和详情页功能。',
			'model'			=> 'WPJAM_Basic'
		]);
	}

	public static function load_crons_page($plugin_page){
		self::set_summary($plugin_page);

		wpjam_register_plugin_page_tab('crons', [
			'title'		=> '定时作业',
			'function'	=> 'list',
			'plural'	=> 'crons',
			'singular'	=> 'cron',
			'model'		=> 'WPJAM_Crons_Admin',
			'order'		=> 20,
			'tab_file'	=> __DIR__.'/wpjam-crons.php'
		]);
	}

	public static function load_extends_page($plugin_page){
		wpjam_register_option('wpjam-extends', [
			'fields'	=> ['WPJAM_Extend', 'get_fields'], 
			'summary'	=> is_network_admin() ? '在管理网络激活将整个站点都会激活！' : '',
			'ajax'		=> false
		]);
	}

	public static function load_bind_page($plugin_page){
		$user_id	= get_current_user_id();
		
		foreach(wpjam_get_user_signups(['bind'=>true]) as $bind_name => $st_obj){
			wpjam_register_plugin_page_tab($bind_name, [
				'title'			=> $st_obj->title,
				'bind_name'		=> $bind_name,
				'capability'	=> 'read',
				'function'		=> 'form',	
				'form_name'		=> $bind_name.'_bind',
				'fields'		=> [$st_obj, 'get_bind_openid_fields'],
				'callback'		=> [$st_obj, 'bind_openid_callback'],
				'submit_text'	=> $st_obj->get_openid($user_id) ? '解除绑定' : '立刻绑定',
				'response'		=> 'redirect'
			]);

			if(!wp_doing_ajax()){
				add_action('admin_footer', [$st_obj, 'bind_script']);
			}
		}
	}

	public static function dashicons_page(){
		$dashicon_css_file	= fopen(ABSPATH.'/'.WPINC.'/css/dashicons.css','r') or die("Unable to open file!");

		$i	= 0;

		$dashicons_html = '';

		while(!feof($dashicon_css_file)) {
			$line	= fgets($dashicon_css_file);
			$i++;

			if($i < 32) continue;

			if($line){
				if (preg_match_all('/.dashicons-(.*?):before/i', $line, $matches)) {
					$dashicons_html .= '<p data-dashicon="dashicons-'.$matches[1][0].'"><span class="dashicons-before dashicons-'.$matches[1][0].'"></span> <br />'.$matches[1][0].'</p>'."\n";
				}elseif(preg_match_all('/\/\* (.*?) \*\//i', $line, $matches)){
					if($dashicons_html){
						echo '<div class="wpjam-dashicons">'.$dashicons_html.'</div>'.'<div class="clear"></div>';
					}
					echo '<h2>'.$matches[1][0].'</h2>'."\n";
					$dashicons_html = '';
				}
			}
		}

		echo '<div class="wpjam-dashicons">'.$dashicons_html.'</div>'.'<div class="clear"></div>';

		fclose($dashicon_css_file);
		?>
		<style type="text/css">
		h2{max-width: 800px; margin:40px 0 20px 0; padding-bottom: 20px; clear: both; border-bottom: 1px solid #ccc;}
		div.wpjam-dashicons{max-width: 800px; float: left;}
		div.wpjam-dashicons p{float: left; margin:0px 10px 10px 0; padding: 10px; width:70px; height:70px; text-align: center; cursor: pointer;}
		div.wpjam-dashicons .dashicons-before:before{font-size:32px; width: 32px; height: 32px;}
		div#TB_ajaxContent p{font-size:20px; float: left;}
		div#TB_ajaxContent .dashicons{font-size:100px; width: 100px; height: 100px;}
		</style>
		<script type="text/javascript">
		jQuery(function($){
			$('body').on('click', 'div.wpjam-dashicons p', function(){
				let dashicon	= $(this).data('dashicon');
				let html 		= '<p><span class="dashicons '+dashicon+'"></span></p><p style="margin-left:20px;">'+dashicon+'<br /><br />HTML：<br /><code>&lt;span class="dashicons '+dashicon+'"&gt;&lt;/span&gt;</code></p>';
				
				$.wpjam_show_modal('tb_modal', html, dashicon, 680);
			});
		});
		</script>
		<?php
	}

	public static function about_page(){
		$jam_plugins = get_transient('about_jam_plugins');

		if($jam_plugins === false){
			$response	= wpjam_remote_request('https://jam.wpweixin.com/api/template/get.json?id=5644');

			if(!is_wp_error($response)){
				$jam_plugins	= $response['template']['table']['content'];
				set_transient('about_jam_plugins', $jam_plugins, DAY_IN_SECONDS );
			}
		}

		?>
		<div style="max-width: 900px;">
			<table id="jam_plugins" class="widefat striped">
				<tbody>
				<tr>
					<th colspan="2">
						<h2>WPJAM 插件</h2>
						<p>加入<a href="http://97866.com/s/zsxq/">「WordPress果酱」知识星球</a>即可下载：</p>
					</th>
				</tr>
				<?php foreach($jam_plugins as $jam_plugin){ ?>
				<tr>
					<th style="width: 100px;"><p><strong><a href="<?php echo $jam_plugin['i2']; ?>"><?php echo $jam_plugin['i1']; ?></a></strong></p></th>
					<td><?php echo wpautop($jam_plugin['i3']); ?></td>
				</tr>
				<?php } ?>
				</tbody>
			</table>

			<div class="card">
				<h2>WPJAM Basic</h2>

				<p><strong><a href="http://blog.wpjam.com/project/wpjam-basic/">WPJAM Basic</a></strong> 是 <strong><a href="http://blog.wpjam.com/">我爱水煮鱼</a></strong> 的 Denis 开发的 WordPress 插件。</p>

				<p>WPJAM Basic 除了能够优化你的 WordPress ，也是 「WordPress 果酱」团队进行 WordPress 二次开发的基础。</p>
				<p>为了方便开发，WPJAM Basic 使用了最新的 PHP 7.2 语法，所以要使用该插件，需要你的服务器的 PHP 版本是 7.2 或者更高。</p>
				<p>我们开发所有插件都需要<strong>首先安装</strong> WPJAM Basic，其他功能组件将以扩展的模式整合到 WPJAM Basic 插件一并发布。</p>
			</div>

			<div class="card">
				<h2>WPJAM 优化</h2>
				<p>网站优化首先依托于强劲的服务器支撑，这里强烈建议使用<a href="https://wpjam.com/go/aliyun/">阿里云</a>或<a href="https://wpjam.com/go/qcloud/">腾讯云</a>。</p>
				<p>更详细的 WordPress 优化请参考：<a href="https://blog.wpjam.com/article/wordpress-performance/">WordPress 性能优化：为什么我的博客比你的快</a>。</p>
				<p>我们也提供专业的 <a href="https://blog.wpjam.com/article/wordpress-optimization/">WordPress 性能优化服务</a>。</p>
			</div>
		</div>
		<style type="text/css">
			.card {max-width: 320px; float: left; margin-top:20px;}
			.card a{text-decoration: none;}
			table#jam_plugins{margin-top:20px; width: 520px; float: left; margin-right: 20px;}
			table#jam_plugins th{padding-left: 2em; }
			table#jam_plugins td{padding-right: 2em;}
			table#jam_plugins th p, table#jam_plugins td p{margin: 6px 0;}
		</style>
	<?php }

	public static function set_summary($plugin_page){
		$summaries	= [
			'wpjam-basic'		=> ['url'=>'https://mp.weixin.qq.com/s/p1VXT8q5z5mXxUCz4xflEw',	'summary'=>'优化设置通过关闭一些不常用的功能来加快 WordPress 的加载'],
			'wpjam-custom'		=> ['url'=>'https://mp.weixin.qq.com/s/kYAQc57YFWCgKneDn2e_bA',	'summary'=>'对网站的前后台的样式进行个性化设置'],
			'wpjam-cdn'			=> ['url'=>'https://mp.weixin.qq.com/s/JJZu8Ml1AW7PxDcy566YzA',	'summary'=>'CDN 加速使用云存储对博客的静态资源进行 CDN 加速'],
			'wpjam-thumbnail'	=> ['url'=>'https://mp.weixin.qq.com/s/S3qDFHPhSuSKlko__g258A',	'summary'=>'缩略图设置让我们无需预定义就可以进行动态裁图，而且还可设置文章和分类缩略图'],
			'wpjam-posts'		=> ['url'=>'https://mp.weixin.qq.com/s/qCeD6-uIzMuEHLQwxPU_NA',	'summary'=>'文章设置把文章编辑的一些常用操作，提到文章列表页面，方便设置和操作'],
			'wpjam-crons'		=> ['url'=>'https://mp.weixin.qq.com/s/H0USuUfU8dzW2d3JOkKbXg',	'summary'=>'定时作业让你可以可视化管理 WordPress 的定时作业'],
			'dashicons'			=> ['url'=>'https://mp.weixin.qq.com/s/Jg4NFimKteFFYpgmYoaC2g',	'summary'=>'Dashicons 功能列出所有的 Dashicons 以及每个的名称和 HTML 代码'],
			'server-status'		=> ['url'=>'https://mp.weixin.qq.com/s/GrE8aYQ0QAGcwx1SFsXHzA',	'summary'=>'系统信息扩展让你在后台就能够快速实时查看当前的系统状态'],
		];

		if(isset($summaries[$plugin_page])){
			$summary	= $summaries[$plugin_page]['summary'];
			$url		= $summaries[$plugin_page]['url'];
			$summary	.= $url ? '，详细介绍请点击：<a href="'.$url.'" target="_blank">'.wpjam_get_plugin_page_setting('menu_title').'</a>。' : '';

			wpjam_set_plugin_page_summary($summary);
		}
	}

	public static function on_admin_init(){
		wpjam_add_menu_page('wpjam-basic', [
			'menu_title'	=> 'WPJAM',
			'icon'			=> 'dashicons-performance',
			'position'		=> '58.99',
			'network'		=> true,
			'subs'			=> [
				'wpjam-basic'		=> [
					'menu_title'	=> '优化设置',
					'order'			=> 99,
					'function'		=> 'option',
					'model'			=> 'WPJAM_Basic',
					'load_callback'	=> [self::class, 'set_summary']
				],
				'wpjam-custom'		=> [
					'menu_title'	=> '样式定制',
					'order'			=> 21,
					'function'		=> 'option',
					'model'			=> 'WPJAM_Custom',
					'load_callback'	=> [self::class, 'set_summary']
				],
				'wpjam-cdn'			=> [
					'menu_title'	=> 'CDN加速',
					'order'			=> 20,
					'function'		=> 'option',
					'model'			=> 'WPJAM_CDN_Setting',
					'load_callback'	=> [self::class, 'set_summary']
				],
				'wpjam-thumbnail'	=> [
					'menu_title'	=> '缩略图设置',
					'order'			=> 19,
					'function'		=> 'option',
					'load_callback'	=> [self::class, 'load_thumbnail_page']
				],
				'wpjam-posts'		=> [
					'menu_title'	=> '文章设置',
					'order'			=> 18,
					'function'		=> 'tab',
					'load_callback'	=> [self::class, 'load_posts_page']
				],
				'wpjam-crons'		=> [
					'menu_title'	=> '定时作业',
					'order'			=> 9,
					'function'		=> 'tab',
					'load_callback'	=> [self::class, 'load_crons_page']
				],
				'server-status'		=> [
					'menu_title'	=> '系统信息',
					'order'			=> 9,
					'function'		=> 'tab',
					'page_file'		=> __DIR__.'/server-status.php',
					'load_callback'	=> [self::class, 'set_summary']
				],
				'wpjam-extends'		=> [
					'menu_title'	=> '扩展管理',
					'order'			=> 3,
					'function'		=> 'option',
					'load_callback'	=> [self::class, 'load_extends_page']
				],
				'dashicons'			=> [
					'menu_title'	=> 'Dashicons',
					'order'			=> 9,
					'function'		=> [self::class, 'dashicons_page'],
					'load_callback'	=> [self::class, 'set_summary']
				],
				'wpjam-about'		=> [
					'menu_title'	=> '关于WPJAM',
					'order'			=> 1,
					'function'		=> [self::class, 'about_page']
				]
			]
		]);

		if(wpjam_get_user_signups(['bind'=>true])){
			wpjam_add_menu_page('wpjam-bind', [
				'parent'		=> 'users',
				'menu_title'	=> '账号绑定',			
				'capability'	=> 'read',
				'function'		=> 'tab',
				'order'			=> 20,
				'load_callback'	=> [self::class, 'load_bind_page']
			]);
		}
	}

	public static function add_separator(){
		$GLOBALS['menu']['58.88']	= ['',	'read',	'separator'.'58.88', '', 'wp-menu-separator'];
	}
}

add_action('wpjam_admin_init',	['WPJAM_Basic_Menu', 'on_admin_init']);
add_action('admin_menu',		['WPJAM_Basic_Menu', 'add_separator']);