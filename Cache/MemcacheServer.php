<?php
namespace Cache;

/**
 * MemcacheServer
 *
 * @author    Pu ShaoWei
 * @date      2017/8/3
 * @package   Cache
 */
class MemcacheServer
{
    protected $memcached;
    protected $cachecfg;
    protected $is_connected = false;
    protected static $instance = false;

    public static function getInstance() {
        if (!self::$instance){
            self::$instance = new self;
        }
        return self::$instance;
    }

    public function __construct($cache='default') {

        $this->connect($cache);

        $this->keys = array();

        $this->cachecfg = 'Default_key.php';  // 建议将memcacheKey 保存为一个配置文件

    }

    public function __destruct() {
        $this->quit();
    }

    public function connect($cache) {

        if (extension_loaded('memcached') && class_exists('\Memcached'))
            $this->memcached = new \Memcached();
        else
            return false;
        $servers = [
                'host' => '192.168.1.1',
                'port' => '11211',
                'persistent' => true,
        ];
        if (!$servers)
            return false;
        foreach ($servers as $server){
            $this->memcached->addServer($server['host'], $server['port'], $server['persistent']);
        }
        $this->is_connected = true;
    }

    //获取cache key 的分类
    public function getClass($class, $key)
    {
        if(!empty($this->cachecfg[$class]))
        {
            $keyFirst = $this->cachecfg[$class];
            $keyAll = str_replace('{0}', '_' . $key, $keyFirst);
            return $keyAll;
        } else {
            return false;
        }
    }

    /**
     * 保存数据
     *
     * @param string $class
     * @param string $key
     * @param string $value
     * @param int $expiration
     */
    public function set($class,$key, $value, $expiration = 0) {

        $key = $this->getClass($class, $key);
        if (!$this->is_connected || strlen($key) > 250 || empty($key)){
            return false;
        }

        return $this->memcached->set($key, $value, $expiration);
    }
    /**
     * 获取数据
     *
     * @param string $class
     * @param string $key
     */
    public function get($class,$key) {

        $key = $this->getClass($class, $key);
        if (!$this->is_connected || strlen($key) > 250 || empty($key)){
            return false;
        }

        return $this->memcached->get($key);
    }
    /**
     * 移除数据
     *
     * @param string $class
     * @param string $key
     */
    public function delete($class,$key) {

        $key = $this->getClass($class, $key);
        if (!$this->is_connected || strlen($key) > 250 || empty($key)){
            return false;
        }
        if(is_array($key)){
            foreach ($key as $k){
                $keys[] = $this->memcached->delete($k);
            }
        }else{
            $keys[] = $this->memcached->delete($key);
        }

        return $keys;
    }

    //为一个key设置一个新的过期时间
    public function touch($class,$key,$expiration) {

        $key = $this->getClass($class, $key);
        if (!$this->is_connected || strlen($key) > 250 || empty($key)){
            return false;
        }

        return $this->memcached->touch($key,$expiration);
    }

    /*
    * 将一个数值元素增加参数offset指定的大小
    *
    * @param string $key
    * @param [int $offset = 1 ]
    */
    public function increment($class,$key,$offset=1 ){

        $key = $this->getClass($class, $key);
        if (!$this->is_connected || strlen($key) > 250 || empty($key)){
            return false;
        }
        return $this->memcached->increment($key,$offset);
    }

    /*
    * 将一个数值元素减少参数offset指定的大小
    *
    * @param string $key
    * @param [int $offset = 1 ]
    */
    public function decrement($class,$key,$offset=1 ){

        $key = $this->getClass($class, $key);
        if (!$this->is_connected || strlen($key) > 250 || empty($key)){
            return false;
        }
        return $this->memcached->decrement($key,$offset);
    }

    //移除缓存中所有数据
    public function flush() {

        if (!$this->is_connected){
            return false;
        }
        return $this->memcached->flush();
    }

    //关闭memcached连接
    protected function quit() {

        if (!$this->is_connected){
            return false;
        }

        return $this->memcached->quit();
    }

    /**
     * 一个更便利的函数，支持将某个函数调用的返回值存入cache
     *
     * @param int $lifetime	unit:s
     * @param string $class
     * @param string $key
     * @param string or array $funcname
     *        如果是函数，则为函数名
     *        如果是类方法，则为：array(类名, 函数名)
     * 		  后可跟funcname调用需要用到的参数列表
     * 		  例子：
     * 		  cacheFunctionCall(300, $key, 'getUserByID', $uid);
     * @return mixed funcname调用的返回值（来自cache或实际调用生成）或者 false（错误）
     */
    public function cacheFuncCall($class, $key, $lifetime, $funcname) {
        $this->setLifetime($lifetime);

        $argarr = func_get_args();
        array_shift($argarr);	//lifetime
        array_shift($argarr);	//class
        array_shift($argarr);	//key
        array_shift($argarr);	//funcname

        $ret = $this->get($class, $key);
        if($ret != false)
            return $ret;

        $ret = call_user_func_array($funcname, $argarr);
        $this->set($class, $key, $ret, $lifetime);

        return $ret;
    }
    //获取服务器池的统计信息
    public function getStats(){
        if (!$this->is_connected)
            return false;

        return $this->memcached->getStats();
    }

    /**
     * [__call 其他属性]
     * @author         Shaowei Pu
     * @CreateTime    2016-12-26T16:31:32+0800
     * @param                               [type] $name  [description]
     * @param                               [type] $param [description]
     * @return                              [type]        [description]
     */
    public function __call($name,$param)
    {
        if(method_exists($this->memcached,$name))
        {
            call_user_func_array(array($this->memcached, $name), $param);
        }
    }

}
