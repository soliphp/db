Soli Db
------------------

Soli Db 提供了一个简单、易用的数据库工具包。

## 目录

* [安装](#安装)
* [配置信息](#配置信息)
* [使用 Connection](#使用-connection)
* [使用事务](#使用事务)
* [配置多数据库连接](#配置多数据库连接)
* [使用 Model](#使用-model)
   * [定义模型](#定义模型)
   * [指定数据库连接服务](#指定数据库连接服务)
   * [指定表名](#指定表名)
   * [initialize 方法](#initialize-方法)
   * [instance 方法](#instance-方法)
   * [新增数据 create](#新增数据-create)
   * [编辑数据 update](#编辑数据-update)
   * [保存数据 save](#保存数据-save)
   * [删除数据 delete](#删除数据-delete)
   * [查询数据](#查询数据)
      * [find 和 findFirst](#find-和-findfirst)
      * [findById](#findbyid)
      * [findByIds](#findbyids)
      * [findByColumn 和 findFirstByColumn](#findbycolumn-和-findfirstbycolumn)
   * [三个重要的 protected 方法](#三个重要的-protected-方法)
* [MIT License](#mit-license)

## 安装

使用 `composer` 安装到你的项目：

    composer require soliphp/db

## 配置信息

Soli Db 采用 `dsn` 的方式配置连接信息，例如下面的格式：

    mysql:host=localhost;port=3307;dbname=testdb;charset=utf8
    mysql:unix_socket=/tmp/mysql.sock;dbname=testdb;charset=utf8
    sqlite:/opt/databases/mydb.sq3
    ...

完整的配置信息，如：

    $dbConfig = [
        'dsn' => 'mysql:host=192.168.56.102;dbname=test;charset=utf8',
        'username' => 'username',
        'password' => 'password',
        'options' => [
            PDO::ATTR_TIMEOUT => 2,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_OBJ,
            // PDO::ATTR_* ...
        ],
    ];

## 使用 Connection

将配置信息 `$dbConfig` 传入 `\Soli\Db\Connection` 的构造参数即可：

    $db = new \Soli\Db\Connection($dbConfig);

    $result = $db->query("SELECT * FROM test");

    var_dump($result);

## 使用事务

    $db = new \Soli\Db\Connection($config);

    $db->beginTrans()

    $deleted = $db->query("DELETE FROM course WHERE student_id = 101");

    if ($deleted === false) {
        $db->rollBack();
    }

    $deleted = $db->query("DELETE FROM student WHERE student_id = 101");

    if ($deleted === false) {
        $db->rollBack();
    }

    $db->commit();

## 配置多数据库连接

我们这里使用[依赖注入容器]作为多数据库连接的管理工具，如下配置一个 `user_db` 和一个 `order_db`：

    $container = new \Soli\Di\Container();

    $container->set('db', function () {                      //db
        return new \Soli\Db\Connection($defaultDbConfig);
    });

    $container->set('user_db', function () {                 //user_db
        return new \Soli\Db\Connection($userDbConfig);
    });

    $container->set('order_db', function () {                //order_db
        return new \Soli\Db\Connection($orderDbConfig);
    });

这个配置会在下面 `Model` 中使用。

## 使用 Model

假设有一个 `user` 表，结构为：

    CREATE TABLE `user` (
      `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
      `name` varchar(20) NOT NULL DEFAULT '',
      `age` tinyint(3) unsigned NOT NULL DEFAULT '0',
      `email` varchar(30) NOT NULL DEFAULT '',
      `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`)
    )

以下文档中使用到的查询基于此表结构。

### 定义模型

    use Soli\Db\Model;

    class User extends Model
    {
    }

这样就完成了一个模型的定义，现在如果我们直接使用这个 `User` 模型，如：

    // 查询主键为 101 的用户
    $user = User::findFirst(101);

将默认使用容器中以 `db` 命名的数据库连接服务（也就是上面 `//db` 行，所设置的服务），且操作的表名为 `user`。

我们也可以为模型指定数据库连接，和表名。

### 指定数据库连接服务

    /**
     * 当前模型访问的数据库连接服务
     */
    public function connectionService()
    {
        return 'user_db';
    }

此时我们使用 User 模型时，访问的是容器中以 `user_db` 命名的数据库连接服务（也就是上面 `//User_db` 行，所设置的服务）。

### 指定表名

    /**
     * 当前模型操作的表名
     */
    public function tableName()
    {
        return 'xxx_user';
    }

此时我们使用 User 模型时，操作的表名为 `xxx_user`。

### initialize 方法

`initialize()` 方法在实例化模型时执行，对于以上指定数据库连接服务、指定表名等操作，也可以在 `initialize` 方法中设置。

    public function initialize()
        // 当前模型访问的数据库连接服务
        $this->connectionService = 'db_service_name';
        // 当前模型操作的表名
        $this->tableName = 'xxx_user';

        // 其他想要执行的需求...
    }

### instance 方法

模型提供了 `instance()` 方法，供外部程序获取模型实例，如：

    // 获取表名
    User::instance()->tableName();

    // 通过 email 获取用户信息
    User::instance()->findByEmail('ueaner@gmail.com');

### 新增数据 create

新增数据，返回插入的主键值。

    $data = [
        'name' => 'ueaner',
        'age' => 28,
        'email' => 'ueaner@gmail.com'
    ];

    // 新增用户
    $userId = User::create($data);

### 编辑数据 update

编辑数据，返回影响行数。

    $data = [
        'name' => 'ueaner',
        'age' => 28,
        'email' => ':email'
    ];

    // 编辑用户ID为 101 的用户信息
    $rowCount = User::update($data, 101);

    // 编辑年龄大于 20 的用户信息
    $rowCount = User::update($data, 'age > 20');

    // 参数绑定
    $binds = [
        ':email' => 'ueaner@soliphp.com',
        ':created_at' => '2015-10-27 08:36:42'
    ];
    $rowCount = User::update($data, 'created_at = :created_at', $binds);

### 保存数据 save

假设 user 表的主键是 `id`，如果保存的数据中有主键，则按主键更新，否则新增一条数据

    $data = [
        'id' => 101, // 保存的数据中有主键，则按主键更新，否则新增一条数据
        'name' => 'ueaner',
        'age' => 28,
        'email' => ':email'
    ];
    $binds = [
        ':email' => 'ueaner@gmail.com',
        ':created_at' => '2015-10-27 08:36:42'
    ];

    $rowCount = User::save($data);
    // 相当于：
    $rowCount = User::update($data, 12);

### 删除数据 delete

删除数据，返回影响行数。

    // 1. 删除主键为 123 的纪录
    User::delete(123);

    // 2. 按传入的条件删除
    User::delete("age > 20 and email == ''");

    // 3. 按传入的条件删除, 并过滤传入的删除条件
    $binds = [':created_at' => '2015-10-27 07:16:16'];
    User::delete("created_at < :created_at", $binds);

### 查询数据

#### find 和 findFirst

`find` 和 `findFirst` 同时适用于以下方式：

    // 1. 获取全部纪录
    User::find();

    // 2. 获取主键为 123 的纪录
    User::find(123);

    // 3. 按传入的条件查询
    User::find("age > 20 and email == ''");

    // 4. 按传入的条件查询, 并过滤传入的查询条件
    $binds = [':created_at' => '2015-10-27 07:16:16'];
    User::find("created_at < :created_at", $binds);

唯一的不同是：

    find       获取的是一个记录列表
    findFirst  获取的是一条记录

以下 `find*` 和 `findFirst*` 开头的函数区别也是如此。

#### findById

顾名思义通过ID（主键）获取一条记录。

    // 获取用户ID为 123 的用户信息
    User::findById(123);

#### findByIds

通过一个ID列表（主键列表）获取相应的记录。

    // 获取用户ID为 123 和 456 的用户信息
    User::findByIds([123, 456]);

#### findBy*Column* 和 findFirstBy*Column*

findBy*Column* / findFirstBy*Column* 通过某一个字段获取数据。

其中的 *Column* 为表中的字段名，如果字段名带有下划线转为驼峰即可。

    // 获取 name 为 'ueaner' 的所有用户记录
    User::findByName('ueaner');

    // 通过 email 字段获取用户信息
    User::findFirstByEmail('ueaner@gmail.com');

    // 通过 create_at 字段获取用户信息
    User::findByCreatedAt('2015-10-27 07:16:16');

### 三个重要的 protected 方法

模型中提供了三个方法用来执行原生 SQL 语句：

    queryAll($sql, $binds = [])     获取多条数据
    queryRow($sql, $binds = [])     获取一条数据
    queryColumn($sql, $binds = [])  获取一个字段

上面所有的 create、update、find* 等方法最终都是通过这三个方法执行的原生 SQL 语句。

我们在写自己的模型方法时也可以调用这三个方法获取 SQL 执行的结果。

## MIT License

MIT Public License


[依赖注入容器]: https://github.com/soliphp/di
