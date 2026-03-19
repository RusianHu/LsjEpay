#!/bin/bash

# ==============================================================================
# 脚本名称: Git 通用强制同步工具
# 适用系统: Ubuntu / Debian / CentOS / macOS
# 功能描述: 自动检测当前目录分支，强制同步远程仓库，覆盖本地修改。
# ==============================================================================

# 颜色定义
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
NC='\033[0m'

echo -e "${YELLOW}[开始同步]${NC} 正在处理当前目录..."

# 1. 检查当前是否在 Git 仓库中
if ! git rev-parse --is-inside-work-tree > /dev/null 2>&1; then
    echo -e "${RED}[错误]${NC} 当前目录 ( $(pwd) ) 不是一个有效的 Git 仓库。"
    exit 1
fi

# 2. 获取最新的远程信息
echo -e "${YELLOW}[1/3]${NC} 正在拉取远程元数据 (git fetch)..."
git fetch --all --prune  # --prune 会顺便清理远程已删除的分支本地残留

if [ $? -ne 0 ]; then
    echo -e "${RED}[错误]${NC} 网络连接失败或无权限访问远程仓库。"
    exit 1
fi

# 3. 自动识别远程库名字 (通常是 origin)
REMOTE_NAME=$(git remote | head -n 1)
if [ -z "$REMOTE_NAME" ]; then
    echo -e "${RED}[错误]${NC} 未关联任何远程仓库。"
    exit 1
fi

# 4. 自动识别远程主分支名 (解决 main/master 冲突)
# 逻辑：优先查找远程 HEAD 指向，找不到则尝试猜测 main 或 master
REMOTE_BRANCH=$(git symbolic-ref refs/remotes/$REMOTE_NAME/HEAD 2>/dev/null | sed "s@^refs/remotes/$REMOTE_NAME/@@")

if [ -z "$REMOTE_BRANCH" ]; then
    # 如果没有 HEAD 指针，尝试手动检测常见分支名
    if git show-ref --verify --quiet refs/remotes/$REMOTE_NAME/main; then
        REMOTE_BRANCH="main"
    elif git show-ref --verify --quiet refs/remotes/$REMOTE_NAME/master; then
        REMOTE_BRANCH="master"
    else
        echo -e "${RED}[错误]${NC} 无法识别远程主分支。请确保远程分支存在。"
        exit 1
    fi
fi

# 5. 执行强制重置
echo -e "${YELLOW}[2/3]${NC} 准备重置到: ${REMOTE_NAME}/${REMOTE_BRANCH}"
git reset --hard "${REMOTE_NAME}/${REMOTE_BRANCH}"

# 6. 结果反馈
if [ $? -eq 0 ]; then
    echo -e "${GREEN}[3/3] 同步成功！${NC}"
    echo -e "当前最新 Commit: $(git log -1 --format='%h - %s (%cr)')"
else
    echo -e "${RED}[错误]${NC} 重置过程中发生未知错误。"
    exit 1
fi
