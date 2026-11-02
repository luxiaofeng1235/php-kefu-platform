/**
 * 访客列表侧边栏组件
 * 显示所有访客列表，支持在线过滤和排序
 */
window.VisitorSidebar = {
    name: 'VisitorSidebar',
    components: {
        VisitorCard: window.VisitorCard
    },
    props: {
        visitors: {
            type: Array,
            default: () => []
        },
        currentToken: {
            type: String,
            default: ''
        },
        filterOnlineOnly: {
            type: Boolean,
            default: false
        },
        filterTodayOnly: {
            type: Boolean,
            default: false
        },
        loading: {
            type: Boolean,
            default: false
        },
        warningThreshold: {
            type: Number,
            default: 15000  // 默认15秒
        },
        identity: {
            type: Object,
            default: null
        }
    },
    emits: ['select', 'toggleFilter', 'toggleFilterToday'],
    template: `
        <div class="visitor-sidebar" style="width: 350px;">
            <!-- 侧边栏头部 -->
            <div class="sidebar-header">
                <h3>
                    访客列表
                    <el-badge :value="displayVisitors.length" :max="999" class="count-badge" />
                </h3>

                <div style="display: flex; align-items: center; gap: 8px;">
                    <span style="font-size: 13px; color: #606266;">仅显示在线</span>
                    <el-tooltip :content="filterOnlineOnly ? '已开启：仅显示在线访客' : '已关闭：显示全部访客'" placement="top">
                        <el-switch
                            :model-value="filterOnlineOnly"
                            @update:model-value="$emit('toggleFilter', $event)"
                            active-color="#13ce66"
                            inactive-color="#DCDFE6"
                        />
                    </el-tooltip>
                    <el-tooltip content="仅显示今日访客" placement="bottom" effect="light">
                        <el-button
                            :type="filterTodayOnly ? 'success' : 'default'"
                            @click="$emit('toggleFilterToday', !filterTodayOnly)"
                        >
                            今日访客
                        </el-button>
                    </el-tooltip>
                </div>
            </div>

            <!-- 访客列表容器 -->
            <div v-loading="loading" class="visitor-list-container" style="height: calc(100vh - 120px); overflow: hidden;">
                <!-- 空状态 -->
                <el-empty
                    v-if="!loading && displayVisitors.length === 0"
                    description="暂无访客"
                    :image-size="100"
                />

                <!-- 虚拟滚动列表 -->
                <div v-else style="height: 100%; overflow-y: auto;" @scroll="handleScroll" ref="scrollContainer">
                    <!-- 上方占位 -->
                    <div :style="{ height: topSpacerHeight + 'px' }"></div>

                    <!-- 可见区域渲染 -->
                    <visitor-card
                        v-for="visitor in visibleVisitors"
                        :key="visitor.token"
                        :visitor="visitor"
                        :active="visitor.token === currentToken"
                        :warning-threshold="warningThreshold"
                        @click="handleSelectVisitor(visitor)"
                    ></visitor-card>

                    <!-- 下方占位 -->
                    <div :style="{ height: bottomSpacerHeight + 'px' }"></div>
                </div>
            </div>
        </div>
    `,
    setup(props, { emit }) {
        const { computed, ref, onMounted, onUnmounted } = Vue;
        const { ElMessage } = ElementPlus;

        // ===== 虚拟滚动配置 =====
        const ITEM_HEIGHT = 180;       // 每个访客卡片高度（px）
        const BUFFER_SIZE = 3;         // 上下缓冲区数量
        const scrollTop = ref(0);      // 当前滚动位置
        const scrollContainer = ref(null); // 滚动容器引用
        const containerHeightValue = ref(600); // 容器高度（动态更新）

        // 更新容器高度的函数
        const updateContainerHeight = () => {
            if (scrollContainer.value) {
                containerHeightValue.value = scrollContainer.value.clientHeight || 600;
            }
        };

        // 监听窗口 resize 事件
        let resizeObserver = null;
        onMounted(() => {
            // 初始化时获取高度
            Vue.nextTick(() => {
                updateContainerHeight();
            });

            // 使用 ResizeObserver 监听容器大小变化（比 window.resize 更精确）
            if (window.ResizeObserver && scrollContainer.value) {
                resizeObserver = new ResizeObserver(() => {
                    updateContainerHeight();
                });
                resizeObserver.observe(scrollContainer.value);
            } else {
                // 降级方案：监听 window resize
                window.addEventListener('resize', updateContainerHeight);
            }
        });

        onUnmounted(() => {
            if (resizeObserver) {
                resizeObserver.disconnect();
            } else {
                window.removeEventListener('resize', updateContainerHeight);
            }
        });

        // ===== 过滤和排序逻辑 =====
        const displayVisitors = computed(() => {
            let items = props.visitors || [];

            const isToday = (value) => {
                const tools = window.VueApp?.Tools;
                let ts = tools && tools.normalizeDateValue ? tools.normalizeDateValue(value) : null;
                if (!ts && typeof value === 'number') {
                    ts = value < 1e12 ? value * 1000 : value;
                }
                if (!ts && typeof value === 'string' && value !== '') {
                    const parsed = Date.parse(value);
                    if (Number.isFinite(parsed)) {
                        ts = parsed;
                    }
                }
                if (!ts) return false;
                const d = new Date(ts);
                const now = new Date();
                return d.getFullYear() === now.getFullYear()
                    && d.getMonth() === now.getMonth()
                    && d.getDate() === now.getDate();
            };

            // 1. 过滤当前客服已接入的访客（不显示在访客列表）
            //    其他客服接入的访客仍然显示，但会有"已被XX客服接入"标签
            if (props.identity?.admin_id) {
                const currentAdminId = Number(props.identity.admin_id);
                items = items.filter(v => {
                    // 如果被接入了
                    if (v.served_by_admin) {
                        const servedByAdminId = Number(v.served_by_admin);
                        // 如果是自己接入的，不显示在访客列表
                        return servedByAdminId !== currentAdminId;
                    }
                    // 未被接入的，正常显示
                    return true;
                });
            }

            // 2. 过滤在线访客
            if (props.filterOnlineOnly) {
                items = items.filter(v => Number(v.is_online) === 1);
            }

            // 3. 今日访客过滤
            if (props.filterTodayOnly) {
                items = items.filter(v => v.connected_at && isToday(v.connected_at));
            }

            // 4. 排序逻辑
            if (window.VueApp && window.VueApp.Tools && window.VueApp.Tools.sortVisitors) {
                items = window.VueApp.Tools.sortVisitors(items, props.warningThreshold);
            } else {
                // 降级排序：在线优先 -> 未读数多的优先
                items = items.slice().sort((a, b) => {
                    const aOnline = Number(a.is_online) === 1 ? 1 : 0;
                    const bOnline = Number(b.is_online) === 1 ? 1 : 0;
                    if (aOnline !== bOnline) return bOnline - aOnline;

                    const aUnread = Number(a.unread_count) || 0;
                    const bUnread = Number(b.unread_count) || 0;
                    return bUnread - aUnread;
                });
            }

            return items;
        });

        // ===== 虚拟滚动计算 =====
        // 计算容器高度（动态获取，已通过 ResizeObserver 实时更新）
        const containerHeight = computed(() => containerHeightValue.value);

        // 可见数量
        const visibleCount = computed(() => {
            return Math.ceil(containerHeight.value / ITEM_HEIGHT) + 1;
        });

        // 起始索引
        const startIndex = computed(() => {
            const index = Math.floor(scrollTop.value / ITEM_HEIGHT);
            return Math.max(0, index - BUFFER_SIZE);
        });

        // 结束索引
        const endIndex = computed(() => {
            const end = startIndex.value + visibleCount.value + BUFFER_SIZE * 2;
            return Math.min(displayVisitors.value.length, end);
        });

        // 可见访客列表
        const visibleVisitors = computed(() => {
            return displayVisitors.value.slice(startIndex.value, endIndex.value);
        });

        // 上方占位高度
        const topSpacerHeight = computed(() => {
            return startIndex.value * ITEM_HEIGHT;
        });

        // 下方占位高度
        const bottomSpacerHeight = computed(() => {
            const remaining = displayVisitors.value.length - endIndex.value;
            return Math.max(0, remaining * ITEM_HEIGHT);
        });

        // 滚动事件处理
        const handleScroll = (event) => {
            scrollTop.value = event.target.scrollTop;
        };

        // ===== 选择访客 =====
        const handleSelectVisitor = (visitor) => {
            // 检查是否被其他客服接入（不是自己）
            if (visitor.served_by_admin) {
                const servedByAdminId = Number(visitor.served_by_admin);
                const currentAdminId = Number(props.identity?.admin_id);

                // 如果是其他客服接入的，才阻止
                if (servedByAdminId !== currentAdminId) {
                    const nickname = visitor.served_by_admin_nickname || '';
                    const displayName = nickname
                        ? `${nickname}(ID=${servedByAdminId})`
                        : `ID=${servedByAdminId}`;
                    ElMessage.warning(`该访客已被客服 ${displayName} 接入`);
                    return;
                }
            }

            // 检查是否离线
            if (Number(visitor.is_online) !== 1) {
                ElMessage.warning('该访客已离线');
                // 仍然可以选中查看历史消息
            }

            emit('select', visitor.token);
        };

        return {
            displayVisitors,
            visibleVisitors,
            topSpacerHeight,
            bottomSpacerHeight,
            scrollContainer,
            handleScroll,
            handleSelectVisitor
        };
    }
};
