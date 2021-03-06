<?php
/**
 * Plugin model.
 *
 * This handles dispatching all plugin actions.
 * @author Jaisen Mathai <jaisen@jmathai.com>
 */
class Plugin extends BaseModel
{
  protected $pluginDir, $activePlugins = array(), $pluginInstances = array();

  public function __construct($params = null)
  {
    parent::__construct();
    if(isset($params['config']))
      $this->config = $params['config'];
    else
      $this->config = getConfig()->get();

    if(isset($this->config->paths->plugins))
      $this->pluginDir = $this->config->paths->plugins;
  }

  public function getActive()
  {
    $active = array();
    if(!isset($this->config->plugins))
      return $active;

    $confPlugins = $this->config->plugins;
    if($confPlugins !== null)
      $pluginsFromConf = (array)explode(',', $confPlugins->activePlugins);
    else
      $pluginsFromConf = array();

    $plugins = $this->getAll();
    foreach($plugins as $plugin)
    {
      if(in_array($plugin, $pluginsFromConf))
        $active[] = $plugin;
    }
    return $active;
  }

  public function getAll()
  {
    if(empty($this->pluginDir) || !is_dir($this->pluginDir))
      return array();
    $dir = dir($this->pluginDir);
    $plugins = array();
    while (($name = $dir->read()) !== false)
    {
      if(is_dir(sprintf('%s/%s', $this->pluginDir, $name)) || substr($name, 0, 1) == '.')
        continue;

      $plugins[] = preg_replace('/Plugin$/', '', basename($name, '.php'));
    }
    sort($plugins);
    return $plugins;
  }

  public function invoke($action, $params = null)
  {
    $output = '';
    foreach($this->pluginInstances as $instance)
    {
      $output .= (string)$instance->$action($params);
    }

    if($output != '')
      echo $output;
  }

  public function isActive($plugin)
  {
    foreach($this->getActive() as $p)
    {
      if($plugin == $p)
        return true;
    }

    return false;
  }

  public function load()
  {
    $this->registerAll();
    return $this;
  }

  public function loadConf($plugin)
  {
    $inst = $this->getInstance($plugin);
    if(!$inst)
      return null;

    $conf = $inst->defineConf();
    if(file_exists($confPath = sprintf('%s/plugins/%s.%s.ini', $this->config->paths->userdata, $_SERVER['HTTP_HOST'], $plugin)))
    {
      $parsedConf = parse_ini_file($confPath);
      foreach($conf as $name => $tmp)
      {
        if(isset($parsedConf[$name]))
          $conf[$name] = $parsedConf[$name];
      }
      return $conf;
    }
    return $conf;
  }

  public function writeConf($plugin, $string)
  {
    $pluginDir = sprintf('%s/plugins', $this->config->paths->userdata);
    if(!is_dir($pluginDir))
    {
      if(!@mkdir($pluginDir))
        $this->logger->warn(sprintf('Could not create directory at %s', $pluginDir));
    }

    if($string !== false)
    {
      $fileCreated = @file_put_contents($pluginConfFile = sprintf('%s/%s.%s.ini', $pluginDir, $_SERVER['HTTP_HOST'], $plugin), $string) !== false;
      if(!$fileCreated)
        $this->logger->warn(sprintf('Could not create file at %s', $pluginConfFile));

      return $fileCreated;
    }
    return false;
  }

  private function getInstance($plugin)
  {
    foreach($this->pluginInstances as $inst)
    {
      if(get_class($inst) == sprintf('%sPlugin', $plugin))
        return $inst;
    }
    return false;
  }

  private function registerAll()
  {
    if(!empty($this->activePlugins))
      return;

    $this->activePlugins = $this->getActive();
    // we verify in getAll that this file exists
    foreach($this->activePlugins as $plugin)
    {
      require sprintf('%s/%sPlugin.php', $this->pluginDir, $plugin);
      $classname = "{$plugin}Plugin";
      $this->pluginInstances[] = new $classname;
    }
  }
}

function getPlugin()
{
  static $plugin;
  if($plugin)
    return $plugin;

  $plugin = new Plugin;
  return $plugin;
}
