FROM centos:8 as c8-systemd
LABEL org.opencontainers.image.authors=tangramor<tangramor@gmail.com>

ENV container docker

RUN (cd /lib/systemd/system/sysinit.target.wants/; for i in *; do [ $i == \
    systemd-tmpfiles-setup.service ] || rm -f $i; done); \
    rm -f /lib/systemd/system/multi-user.target.wants/*;\
    rm -f /etc/systemd/system/*.wants/*;\
    rm -f /lib/systemd/system/local-fs.target.wants/*; \
    rm -f /lib/systemd/system/sockets.target.wants/*udev*; \
    rm -f /lib/systemd/system/sockets.target.wants/*initctl*; \
    rm -f /lib/systemd/system/basic.target.wants/*;\
    rm -f /lib/systemd/system/anaconda.target.wants/*; \
    yum install -y epel-release dnf-plugins-core; \
    sed -i 's/#baseurl=https:\/\/download\.example\/pub/baseurl=https:\/\/mirrors\.aliyun\.com/g' /etc/yum.repos.d/epel.repo; \
    sed -i 's/metalink=/#metalink=/g' /etc/yum.repos.d/epel.repo; \
    cat /etc/yum.repos.d/epel.repo; \
    dnf repolist epel -v; \
    yum makecache;

VOLUME [ "/sys/fs/cgroup" ]
CMD ["/usr/sbin/init"]


#-----------------------------------------------------------------------
FROM c8-systemd as down-code
LABEL org.opencontainers.image.authors=tangramor<tangramor@gmail.com>

ENV VERSION "1.15"

# COPY EwoMail /root/EwoMail
RUN yum install -y git \
    && cd /root \
    && git clone https://gitee.com/laowu5/EwoMail.git --depth 1 \
    && cd EwoMail \
    && git fetch --unshallow \
    && git checkout ${VERSION} \
    && rm -rf .git


#-----------------------------------------------------------------------
FROM c8-systemd
LABEL org.opencontainers.image.authors=tangramor<tangramor@gmail.com>

# 设置时区
ENV TZ "Asia/Shanghai"

COPY --from=down-code /root/EwoMail /root/EwoMail

COPY start.sh /root/EwoMail/install/start.sh
COPY init_env.sh /root/EwoMail/install/init_env.sh
COPY init.php /root/EwoMail/install/init.php

COPY ewomail-admin-import /root/EwoMail/ewomail-admin

RUN cd /root/EwoMail/install \
    && sh ./start.sh

EXPOSE 25 109 110 143 465 587 993 995 7000 7010 8000 8010 8020

VOLUME ["/ewomail/mail"]

ENTRYPOINT ["/usr/sbin/init"]
