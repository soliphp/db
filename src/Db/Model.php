<?php
/**
 * @author ueaner <ueaner@gmail.com>
 */
namespace Soli\Db;

use Soli\Di\Container;
use Soli\Di\ContainerInterface;
use Soli\Di\ContainerAwareInterface;
use Soli\Di\ContainerAwareTrait;

/**
 * 模型
 *
 * @property \Soli\Db\Connection $db
 * @property \Soli\Di\ContainerInterface $container
 */
abstract class Model implements ContainerAwareInterface
{
    use ContainerAwareTrait;
    use Query;

    /** @var string $connectionService */
    protected $connectionService;

    /**
     * Model constructor.
     *
     * @param \Soli\Di\ContainerInterface|null $container
     */
    final public function __construct(ContainerInterface $container = null)
    {
        if (!is_object($container)) {
            $container = Container::instance();
        }

        if (method_exists($this, 'initialize')) {
            // 初始化方法可以设置：connectionService，tableName，primaryKey
            $this->initialize();
        }

        $container->set(get_called_class(), $this);
        // 虽然尽量避免使用 new，而是使用 instance() 方法取
        // 但也保证两者拿到的结构是一样的
        $this->container = $container;
    }

    /**
     * 获取 Model 对象实例
     *
     * @return static
     */
    public static function instance()
    {
        return Container::instance()->get(get_called_class());
    }

    /**
     * 获取数据库连接服务名称
     *
     * @return string
     */
    public function connectionService()
    {
        return $this->connectionService ? $this->connectionService : 'db';
    }

    /**
     * 执行一条 SQL 语句
     *
     * @param string $sql SQL语句
     * @param array  $binds 绑定数据
     * @param string $fetchMode column|row|all 返回的数据结果类型
     * @return array|int|string
     *   插入数据返回插入数据的主键ID，更新/删除数据返回影响行数
     *   查询语句则根据 $fetchMode 返回对应类型的结果集
     * @throws \PDOException
     */
    protected function query($sql, $binds = [], $fetchMode = 'all')
    {
        return $this->db->query($sql, $binds, $fetchMode);
    }

    /**
     * 查询 SQL 语句返回结果的所有行
     *
     * @param string $sql SQL语句
     * @param array $binds 绑定条件
     * @return array
     */
    protected function queryAll($sql, $binds = [])
    {
        return $this->query($sql, $binds, 'all');
    }

    /**
     * 查询 SQL 语句返回结果的第一行
     *
     * @param string $sql SQL语句
     * @param array $binds 绑定条件
     * @return array
     */
    protected function queryRow($sql, $binds = [])
    {
        return $this->query($sql, $binds, 'row');
    }

    /**
     * 查询 SQL 语句中第一个字段的值
     *
     * @param string $sql SQL语句
     * @param array $binds 绑定条件
     * @return int|string
     */
    protected function queryColumn($sql, $binds = [])
    {
        return $this->query($sql, $binds, 'column');
    }

    /**
     * 获取 Db 连接或 Container 中的某个 Service
     *
     * @param string $name
     * @return mixed
     */
    public function __get($name)
    {
        $container = $this->container;

        if ($name == 'db') {
            $this->db = $container->get($this->connectionService());
            return $this->db;
        }

        if ($container->has($name)) {
            $this->$name = $container->get($name);
            // 将找到的服务添加到属性, 以便下次直接调用
            return $this->$name;
        }

        trigger_error("Access to undefined property $name");
        return null;
    }
}
