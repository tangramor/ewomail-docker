# EwoMail 容器镜像

本镜像基于 [EwoMail 1.15](https://gitee.com/laowu5/EwoMail/tree/1.15) 版本代码构建。

需要注意的是，因为 WSL 本身的问题，本镜像目前**不能**在 WSL 上运行。


## 使用方法

在当前目录下创建 `.env` 文件，内容类似下面的，分别是你需要部署的邮箱域名和 MySQL root 密码：

```ini
DOMAIN=example.com
MYSQL_ROOT_PASSWORD=mypassword123
```

修改 `docker-compose.yml` 里 `volumes` 映射的路径，例如 `/home/ewomail/vmail`、`/home/ewomail/mysql`。

修改完成后，在当前目录运行 `docker-compose up -d` 启动容器组，使用 `docker-compose logs -f` 观察输出情况，等到 mysql 容器运行输出类似下面的日志，我们就可以开始初始化数据了。

```
mysql_1       | 2021-09-24T09:37:36.602721Z 0 [System] [MY-011323] [Server] X Plugin ready for connections. Bind-address: '::' port: 33060, socket: /var/run/mysqld/mysqlx.sock
```


### 初始化

运行如下命令：

```shell
# 初始化环境配置
docker exec -i ewomail /root/EwoMail/install/init_env.sh

# 重启或不重启都可以
docker restart ewomail
```


### 配置域名解析

根据[官方文档](http://doc.ewomail.com/docs/ewomail/domain_dns)的配置方法，配置域名解析，然后就可以使用了。

如果仅仅是测试，需要修改 `hosts` 文件，包括客户端和宿主机服务器上的，例如：

```
192.168.200.21	example.com mail.example.com smtp.example.com imap.example.com
```


### 端口、密码

* webmail 端口： 8000, 7000 (ssl)
* 邮箱后台端口： 8010, 7010 (ssl)
* web 数据库管理： 8020 (ssl)
* 邮箱服务端口： 25，143，993，995，587，110，465

标记 ssl 的端口需要 https 访问

邮箱管理后台的默认密码是 `ewomail123`，建议修改一个复杂的密码。
