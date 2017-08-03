<?php
namespace library\Base\Ckeditor;
	

/**
 *---------------------------------------------------------------------------
 * 	--> 前端输出在 Header 下面位置，因为Ck实在是太大了呀
 * 	<?php echo library\Base\Tools::script('libs/js/plugins/ckeditor/ckeditor.js');?>
 *---------------------------------------------------------------------------
 *  --> 后端输出方式
 *  library\Base\CkEditor\CkEditorInit::assign( array 输出到页面的内容 , array 容器 );
 *----------------------------------------------------------------------------
 * 
 * CkEditorInit Ckeditor 输出工具
 * 
 * @version ${Id}$
 * @author ShaoWeiPu 
 */

class CkEditorInit 
{	
	/**
	 * [$strategy_key 分类key]
	 * @var string
	 */
	protected static $strategy_key = '';

	/**
	 * [assign description]
	 * @author 		Shaowei Pu 
	 * @CreateTime	2017-04-18T16:51:23+0800
	 * @return                              [type] [description]
	 */
	public static function assign( $data = '', $strategy = [ 'default' => ['introduce','contact_desc','problem_desc']] , $asyn = 'ume/upload'  )
	{	
		self::$strategy_key = key( $strategy );
		return self::assignHTML( self::chooseBox( $strategy[self::$strategy_key] ,$data ) ,$asyn );
	}

	/**
	 * [getVarName description]
	 * @author 		Shaowei Pu 
	 * @CreateTime	2017-04-18T18:25:22+0800
	 * @param                               string $strategy [description]
	 * @return                              [type]           [description]
	 */
	protected function getInstanceConfig( $strategy )
	{
		$list = [
			'default' => [
				'introduce'    => self::assignIntroduce(),
				'contact_desc' => self::assignSmiple(),
				'problem_desc' => self::assignSmiple(),
			]
		];
		$export = array_key_exists(self::$strategy_key ,$list ) ? $list[self::$strategy_key] : [];
		return isset( $export[$strategy] ) ? $export[$strategy] : self::assignIntroduce();
	}

	/**
	 * [chooseBox 选择容器]
	 * @author 		Shaowei Pu 
	 * @CreateTime	2017-04-18T17:21:08+0800
	 * @param                               [type] $strategy [description]
	 * @return                              [type]           [description]
	 */
	protected  function chooseBox( $strategy ,$data )
	{	
		$container = []; 
		array_map( function( $val ) use ( &$container ,$data ){
			$container['html'] 	 .= '<textarea hidden class="con_'.$val.'" >'.self::getTypeData($data ,$val).'</textarea>';
			$container['val']  	 .= "var i_".$val." = window.document.getElementsByClassName('con_".$val."')[0].value;	";
			$container['config'] .= "var c_".$val." = ".self::getInstanceConfig( $val )."; ";
			$container['init'] 	 .= "var ck_".$val."= CKEDITOR.replace('".$val."',c_".$val."); ";
			$container['content'].= "ck_".$val.".setData(i_".$val."); ";
		} , $strategy );

	/* -------------------------------------- 视图输出 -------------------------------------- */

return  <<<CKEDITOR
			 </script>
		{$container['html']}
			 <script type="text/javascript">
		{$container['val']}

		{$container['config']}

		{$container['init']}

		{$container['content']}
			  </script>
CKEDITOR;
	}

	/**
	 * [getTypeData 获取数组信息]
	 * @author 		Shaowei Pu 
	 * @CreateTime	2017-04-18T17:36:24+0800
	 * @param                               [type] $data [description]
	 * @return                              [type]       [description]
	 */
	protected function getTypeData( array $data, $key )
	{
		$key_collection = [
			'default' => [ 
					'introduce'    => 'event_introduce',
					'contact_desc' => 'event_contact_desc',
					'problem_desc' => 'event_problem_desc'
				],
			'hotel'  => [ 
					'info'	 	   => 'hotel_info',
					'policy' 	   => 'hotel_policy',
					'rim'	   	   => 'hotel_rim',
					'introduce'	   => 'hotel_introduce',
				],
			'scenic' => [
					'introduce'	   => 'scenic_introduce',
				],
		];
		// ------------------ 各模块特殊键值对处理 ------------------ //
		isset($key_collection[self::$strategy_key][$key])  && $key = $key_collection[self::$strategy_key][$key];

		switch ( $key ){
			case in_array( $key, ['event_introduce', 'event_contact_desc'] ):
				$key = str_replace('event_', '', $key);
				return isset($data[$key]) ? stripcslashes($data[$key]) : '';
				break;
			case in_array( $key, ['hotel_info', 'hotel_policy','hotel_rim'] ):
				$key = str_replace('hotel_', '', $key);
				return isset($data[$key]) ? String::htmlentitiesDecodeUTF8($data[$key]) : '';
				break;
			case 'event_problem_desc':
				$key = str_replace('event_', '', $key);
		        return isset($data[$key]) && !empty($data['title']) ? stripcslashes($data[$key]) : self::getProblemDemo();
				break;
			case 'hotel_introduce':
				$key = str_replace('hotel_', '', $key);	
		        return isset($data[$key]) ? stripcslashes(stripcslashes($data[$key])) : ''; 
				break;
			case 'scenic_introduce':
				$key = str_replace('scenic_', '', $key);	
		        return isset($data[$key]) ? stripcslashes(stripcslashes($data[$key])) : ''; 
				break;
			default:
				return isset($data[$key]) ? $data[$key] : '';
				break;
		}
	}

	/**
	 * [BackendDriver CkEditor 后端驱动程序]
	 * @author 		Shaowei Pu 
	 * @CreateTime	2017-04-20T20:31:32+0800
	 * @param                               Yaf\Request\Http $request [description]
	 */
	public static function BackendDriver(\Yaf\Request\Http $request)
	{
		if( $request->isGet()){
			$query =  $request->getQuery();
			if( isset( $query['resource-url'] ) && isset($query['resource-url'] ) ){
				self::oembedOperation( $query );
			}
		}
		if ( $request->isPost()) {
			$ck_upload 	 =  !empty( $request->getQuery('CKEditor','')) ? true : false ;
			$ck_copy	 =  !empty( $request->getPost('ckCsrfToken'))  ? true : false ;

			$response = $ck_upload || $ck_copy ? Basefunc::uploadImage('upload') : Basefunc::uploadImage('upfile');
			$return_data = array(
				'originalName' => $_FILES['upfile']['name'],
	            "size" => $_FILES['upfile']['size'],
	            "type" => $_FILES['upfile']['type'],
			);
			if ($response['code'] == 200) {
				$image_url = Basefunc::getImageUrl($response['img_id']);
				$return_data['name'] 		= basename($image_url);
				$return_data['url'] 		= $image_url;
				$return_data['state'] 		= 'SUCCESS';
			} else {
				$return_data['state']		= $response['message'];	
			}

			if( $ck_upload ){
				$func_num =  $request->getQuery('CKEditorFuncNum','');
				if($return_data['state'] == 'SUCCESS'  ){
					echo '<script type="text/javascript"> window.parent.CKEDITOR.tools.callFunction("'.$func_num.'", " '.$return_data['url'].'", ""); </script>'; 
				}else{
					echo '<script type="text/javascript"> window.parent.CKEDITOR.tools.callFunction("'.$func_num.'", " '.$return_data['state'].'", ""); alert("'.$return_data['state'].'")</script>'; 
				}
				die;
			}elseif( $ck_copy ){
				echo json_encode([
						'fileName' => $return_data['name'],
						'uploaded' => $return_data['state'] == 'SUCCESS' ? '1' : '0',
						'url'	   => $return_data['url'],
						'error'	   => [
								'number'  => time(),
								'message' => $return_data['state']
						]
				]);die;
			}
				echo json_encode($return_data);die;
		}

		return Tools::json(array('state' =>  'Fails'));	
	}

	/**
	 * [oembedAction omebed接口]
	 * @author 		Shaowei Pu 
	 * @CreateTime	2017-04-17T19:25:59+0800
	 * @return                              [type] [description]
	 */
	public static function oembedOperation( array $query )
	{
		$resource_url = $query['resource-url'];
		$callback 	  = $query['callback'];
		$meta 		  = get_meta_tags($resource_url);
		$complete_url = parse_url($resource_url);
		$url =	self::getHtmlSuffix( $complete_url['path'] );

		switch ($complete_url['host']) {
			case 'v.qq.com':
				$response = self::tencentVideo( $resource_url , $meta );
				break;
			case 'v.youku.com':
				$response = self::youkuVideo( $resource_url , $meta, $url );
				break;
			default:
				echo  "$callback && $callback(alert('仅支持腾讯视频与优酷视频'))";
				exit;
				break;
		}
   		 echo "$callback && $callback(".json_encode( 
   		 	array_merge($response,[
	   		 	"url" 	        => $resource_url,
			    "type"	        => "video",	
			    "version"       => "1.0",	
			    "cache_age"     => 86400	// 60 * 60 * 24
   		 	]) ).")"; exit;
	}

	/**
	 * [getHtmlSuffix 获得靠近.html 字符串]
	 * @author 		Shaowei Pu 
	 * @CreateTime	2017-04-18T16:24:38+0800
	 * @param                               [type] $url_patch [description]
	 * @return                              [type]            [description]
	 */
	protected function getHtmlSuffix( $url_patch )
	{
		preg_match_all('/[^\.\/]+\.[^\.\/]+$/', $url_patch, $url_patch);
		return  strstr($url_patch[0][0],'.html',true);
	}


	/**
	 * [assignIntroduce 活动发布详细的输出]
	 * @author 		Shaowei Pu 
	 * @CreateTime	2017-04-18T16:48:42+0800
	 * @return                              [type] [description]
	 */
	public static function assignIntroduce ()
	{
		return json_encode([
	      'toolbarGroups' => [
	        [ 'name' => 'clipboard', 'groups' => [ 'undo', 'clipboard' ] ],
	        [ 'name' => 'editing', 'groups' => [ 'find', 'selection', 'spellchecker', 'editing' ] ],
	        '/',
	        [ 'name' => 'forms', 'groups' => [ 'forms' ] ],
	        [ 'name' => 'basicstyles', 'groups' => [ 'basicstyles', 'cleanup' ] ],
	        [ 'name' => 'styles', 'groups' => [ 'styles' ] ],
	        [ 'name' => 'colors', 'groups' => [ 'colors' ] ],
	        [ 'name' => 'paragraph', 'groups' => [ 'align', 'list', 'indent', 'blocks', 'bidi', 'paragraph' ] ],
	        [ 'name' => 'insert', 'groups' => [ 'insert' ] ],
	        [ 'name' => 'links', 'groups' => [ 'links' ] ],
	        [ 'name' => 'tools', 'groups' => [ 'tools' ] ],
	        [ 'name' => 'others', 'groups' => [ 'others' ] ],
	        [ 'name' => 'about', 'groups' => [ 'about' ] ],
	        [ 'name' => 'document', 'groups' => [ 'document', 'doctools', 'mode' ] ]
	      ],
	      'removeButtons' => 'image,Flash,Superscript,Subscript,Strike,Print,Preview,NewPage,Save,Templates,Replace,Find,SelectAll,Scayt,Form,Checkbox,Radio,TextField,Textarea,Select,Button,ImageButton,HiddenField,Indent,Outdent,CreateDiv,Language,BidiRtl,BidiLtr,Anchor,Iframe,PageBreak,SpecialChar,Format,Styles,ShowBlocks,About'
		]);
	}
	
	/**
	 * [assignSmiple 基础版本]
	 * @author 		Shaowei Pu 
	 * @CreateTime	2017-04-18T16:49:01+0800
	 * @return                              [type] [description]
	 */
	public static function assignSmiple(){
		return json_encode([
	      'toolbarGroups' => [
	        [ 'name' => 'clipboard', 'groups' => [ 'undo', 'clipboard' ] ],
	        [ 'name' => 'editing', 'groups' => [ 'find', 'selection', 'spellchecker', 'editing' ] ],
	        [ 'name' => 'forms', 'groups' => [ 'forms' ] ],
	        [ 'name' => 'basicstyles', 'groups' => [ 'basicstyles', 'cleanup' ] ],
	        [ 'name' => 'styles', 'groups' => [ 'styles' ] ],
	        [ 'name' => 'colors', 'groups' => [ 'colors' ] ],
	        [ 'name' => 'paragraph', 'groups' => [ 'align', 'list', 'indent', 'blocks', 'bidi', 'paragraph' ] ],
	        [ 'name' => 'insert', 'groups' => [ 'insert' ] ],
	        [ 'name' => 'links', 'groups' => [ 'links' ] ],
	        [ 'name' => 'tools', 'groups' => [ 'tools' ] ],
	        [ 'name' => 'others', 'groups' => [ 'others' ] ],
	        [ 'name' => 'about', 'groups' => [ 'about' ] ],
	        [ 'name' => 'document', 'groups' => [ 'document', 'doctools', 'mode' ] ]
	      ],
	      'removeButtons' => 'image,BGColor,TextColor,Smiley,HorizontalRule,Image,Embed,Table,Paste,PasteText,PasteFromWord,Flash,Superscript,Subscript,Strike,Print,Preview,NewPage,Save,Templates,Replace,Find,SelectAll,Scayt,Form,Checkbox,Radio,TextField,Textarea,Select,Button,ImageButton,HiddenField,Indent,Outdent,CreateDiv,Language,BidiRtl,BidiLtr,Anchor,Iframe,PageBreak,SpecialChar,Format,Styles,ShowBlocks,About'
		]);
	}

	
	/**
	 * [tencentVideo 腾讯视频]
	 * @author 		Shaowei Pu 
	 * @CreateTime	2017-04-17T19:26:09+0800
	 * @param                               [type] $resource_url [description]
	 * @param                               [type] $meta         [description]
	 * @return                              [type]               [description]
	 */
	protected function tencentVideo( $resource_url ,$meta)
	{
		\Yaf\Loader::import(dirname(__FILE__).'/phpQuery/phpQuery.php');
		\phpQuery::newDocumentFile($resource_url);    

		 libxml_use_internal_errors(true);
		// 腾讯视频的真实URL 其实藏在这 因为爬了下网站所以有点慢
		$url = pq('link[rel="canonical"]')[0]->attr('href');
		$vid = self::getHtmlSuffix(parse_url($url,PHP_URL_PATH));

		// 输出	
		$src = 'https://v.qq.com/iframe/player.html?vid='.$vid.'&tiny=0;auto=0';
		return  [
			"title"            => $meta['title'],
			"author"           => $meta['author'],
			"description"      => $meta['description'],
			"thumbnail_url"    => $meta['twitter:image'],
			'thumbnail_height' => 1079,
			'thumbnail_width'  => 1920,
		    "html"         	   => "<div><div style=\"left: 0px; width: 100%; height: 0px; position: relative; padding-bottom: 56.25%;\"><iframe src=\"{$src}\" frameborder=\"0\" allowfullscreen style=\"top: 0px; left: 0px; width: 100%; height: 100%; position: absolute;\"></iframe></div></div>"
   		 ];
	}

	/**
	 * [youkuVideo 优酷视频]
	 * @author 		Shaowei Pu 
	 * @CreateTime	2017-04-17T19:26:17+0800
	 * @param                               [type] $resource_url [description]
	 * @param                               [type] $meta         [description]
	 * @return                              [type]               [description]
	 */
	protected function youkuVideo( $resource_url ,$meta ,$url )
	{
		$src = '//player.youku.com/embed/'.str_replace('id_', '', $url);
		return  [
		    "title"         =>  $meta['title'],
		    "html"          => "<div><div style=\"left: 0px; width: 100%; height: 0px; position: relative; padding-bottom: 56.25%;\"><iframe src=\"{$src}\" frameborder=\"0\" allowfullscreen scrolling=\"no\" style=\"top: 0px; left: 0px; width: 100%; height: 100%; position: absolute;\"></iframe></div></div>"
   		 ];
	}

	/**
	 * [assignHTML 全局视图输出]
	 * @author 		Shaowei Pu 
	 * @CreateTime	2017-04-21T14:43:21+0800
	 * @param                               [type] $assign_box [description]
	 * @param                               [type] $asyn       [description]
	 * @return                              [type]             [description]
	 */
	protected function assignHTML( $assign_box,$asyn )
	{
	/* -------------------------------------- 视图输出 -------------------------------------- */
return  <<<CKEDITOR
	<script type="text/javascript">
		if ( CKEDITOR.env.ie && CKEDITOR.env.version < 9 )
      		CKEDITOR.tools.enableHtml5Elements( document );
		
		CKEDITOR.editorConfig = function( config ){
		  config.embed_provider = '/{$asyn}?resource-url={url}&callback={callback}';
		  config.language = 'zh-cn';
		  
		  //去掉图片预览英文
		  config.image_previewText = ' ';
		  config.resize_enabled = true;
		  config.startupOutlineBlocks = false;
		  config.startupFocus = false;

		  // 扩展插件
		  config.extraPlugins = 'embedbase,notification,widgetselection,lineutils,notificationaggregator,widget,embed,uploadimage,uploadwidget,filetools,image2'; 
		  config.height= 300;
		  config.filebrowserImageBrowseUrl= "{$asyn}";
		  config.filebrowserImageUploadUrl= "{$asyn}";
		  config.filebrowserBrowseUrl     = "{$asyn}";
		  config.filebrowserUploadUrl     =	"{$asyn}";
		  config.filebrowserWindowWidth   = '800';  //“浏览服务器”弹出框的size设置
		  config.disableObjectResizing =false;
		};

		{$assign_box}
CKEDITOR;
	}


	/**
	 * [getProblemDemo 默认常见问题]
	 * @author 		Shaowei Pu 
	 * @CreateTime	2017-04-20T20:40:30+0800
	 * @return                              [type] [description]
	 */
	public function getProblemDemo()
	{
		/* -------------------------------------- 视图输出 -------------------------------------- */
return  <<<PROBLEM
		  <p> <span style="font-family: 微软雅黑, &#39;Microsoft YaHei&#39;; font-size: 12px; line-height: 1.125;">问：付款成功后，怎么查询订单？<br/>答：1、通过购票页面“我的订单”登录查询；<br/>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;2、通过购票成功后收到的短信链接查询；<br/>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;3、若以上方式均查询不到，可在工作日期间（周一到周五 9:30 - 18:30）拨打客服电话查询。<br/><br/>问：订单支付界面不跳转或支付不成功？<br/>答：可刷新界面或重新提交订单，或拨打客服电话。<br/><br/>问：买完票行程有变，能退票么？<br/>答：由于演出票的特殊性，门票一经售出，一般不予退换，具体可向主办方咨询。<br/><br/>问：预订手机号码输错，收不到确认短信怎么办？<br/>答：可在工作日期间（周一到周五 9:30 - 18:30），拨打客服电话咨询。<br/></span> </p>
PROBLEM;
	}
}