<?php
/**
 * @author ueaner <ueaner@gmail.com>
 */
namespace Soli\Db;

use PDO;
use Exception;
use PDOException;

/**
 * Db Connection Wrapper
 */
class Connection
{
    /**
     * PDO 实例
     *
     * @var \PDO
     */
    protected $pdo = null;

    /**
     * 预处理
     *
     * @var \PDOStatement
     */
    protected $stmt = null;

    /**
     * 默认 PDO 连接选项
     *
     * @var array
     */
    protected $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];

    /**
     * 配置信息
     *
     * @var array
     */
    protected $config = [];

    /**
     * Connection constructor.
     *
     * @param array|\ArrayAccess $config {
     *     @var string $dsn
     *     @var string $username
     *     @var string $password
     *     @var array  $options
     * }
     */
    public function __construct($config)
    {
        $this->config = $config;
        $this->open();
    }

    /**
     * 连接数据库
     *
     * @return \PDO
     */
    protected function open()
    {
        // 关闭连接
        $this->close();

        $dsn = $this->config['dsn'] ?? null;

        $username = $this->config['username'] ?? null;
        $password = $this->config['password'] ?? null;
        $options  = $this->config['options'] ?? null;

        if (empty($options)) {
            $options = $this->options;
        } else {
            $options = array_diff_key($this->options, $options) + $options;
        }

        $this->pdo = new PDO($dsn, $username, $password, $options);

        return $this->pdo;
    }

    /**
     * 关闭连接
     */
    public function close()
    {
        $this->stmt = null;
        $this->pdo = null;
    }

    /**
     * 获取 PDO 实例
     *
     * @return \PDO
     */
    public function getPdo()
    {
        return $this->pdo;
    }

    /**
     * 查询 SQL 语句返回结果的所有行
     *
     * @param string $sql SQL语句
     * @param array $binds 绑定条件
     * @return array
     */
    public function queryAll($sql, $binds = [])
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
    public function queryRow($sql, $binds = [])
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
    public function queryColumn($sql, $binds = [])
    {
        return $this->query($sql, $binds, 'column');
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
     */
    public function query($sql, array $binds = [], $fetchMode = 'all')
    {
        try {
            return $this->queryInternal($sql, $binds, $fetchMode);
        } catch (PDOException $e) {
            if ($this->causedByLostConnection($e)) {
                $this->open();
                return $this->queryInternal($sql, $binds, $fetchMode);
            }
            throw $e;
        }
    }

    /**
     * 执行一条 SQL 语句（内部方法）
     *
     * @param string $sql SQL语句
     * @param array  $binds 绑定数据
     * @param string $fetchMode column|row|all 返回的数据结果类型
     * @return array|int|string
     *   插入数据返回插入数据的主键ID，更新/删除数据返回影响行数
     *   查询语句则根据 $fetchMode 返回对应类型的结果集
     */
    protected function queryInternal($sql, array $binds = [], $fetchMode = 'all')
    {
        // prepare -> binds -> execute
        $this->stmt = $this->pdo->prepare($sql);
        $this->stmt->execute($binds);

        list($type) = explode(' ', $sql, 2);
        $type = strtoupper($type);

        // 返回相应操作类型的数据结果
        switch ($type) {
            case 'INSERT':
                return $this->lastInsertId();
            case 'DELETE':
                // no break
            case 'UPDATE':
                return $this->rowCount();
            default:
                // SELECT, USE, SHOW, DESCRIBE, EXPLAIN ...
                return $this->fetchMode($fetchMode);
        }
    }

    /**
     * 返回最后插入行的 ID 或序列值，数据库需要将主键设置为自增
     *
     * @return int
     */
    public function lastInsertId()
    {
        return $this->pdo->lastInsertId();
    }

    /**
     * 返回 SQL 语句影响行数
     *
     * @return int
     */
    public function rowCount()
    {
        return $this->stmt->rowCount();
    }

    /**
     * 根据 fetchMode 获取相应的查询结果，这里的 fetchMode 不是 PDO::FETCH_* fetchStyle 常量
     *
     * @param string $fetchMode row|column|all
     * @return array|string
     */
    protected function fetchMode($fetchMode)
    {
        switch (strtoupper($fetchMode)) {
            case 'ROW':
                // 获取一行数据
                return $this->stmt->fetch();
            case 'COLUMN':
                // 获取一个字段值
                return $this->stmt->fetchColumn();
            case 'ALL':
                // no break
            default:
                // 获取完整的查询结果
                return $this->stmt->fetchAll();
        }
    }

    /**
     * 确定异常是否由连接丢失引起的
     *
     * @param \Exception $e
     * @return bool
     */
    protected function causedByLostConnection(Exception $e)
    {
        return contains($e->getMessage(), [
            'server has gone away',
            'no connection to the server',
            'Lost connection',
            'is dead or not enabled',
            'Error while sending',
            'decryption failed or bad record mac',
            'server closed the connection unexpectedly',
            'SSL connection has been closed unexpectedly',
            'Error writing data to the connection',
            'Resource deadlock avoided',
            'Transaction() on null',
            'child connection forced to terminate due to client_idle_limit',
        ]);
    }

    // 事务

    /**
     * 开启事务，关闭自动提交
     *
     * @return bool
     */
    public function beginTrans()
    {
        try {
            return $this->pdo->beginTransaction();
        } catch (PDOException $e) {
            if ($this->causedByLostConnection($e)) {
                $this->open();
                return $this->pdo->beginTransaction();
            }
            throw $e;
        }
    }

    /**
     * 提交更改，开启自动提交
     */
    public function commit()
    {
        $this->pdo->commit();
    }

    /**
     * 回滚更改，开启自动提交
     */
    public function rollBack()
    {
        $this->pdo->rollBack();
    }

    /**
     * 检查是否在一个事务内
     *
     * @return bool
     */
    public function inTrans()
    {
        return $this->pdo->inTransaction();
    }
}
