/**
 * 消息面板组件
 * 显示消息列表，自动滚动到底部
 */
window.MessagePanel = {
    name: 'MessagePanel',
    components: {
        MessageItem: window.MessageItem
    },
    props: {
        messages: {
            type: Array,
            default: () => []
        },
        identity: {
            type: Object,
            default: null
        },
        currentVisitorToken: {
            type: String,
            default: ''
        },
        loading: {
            type: Boolean,
            default: false
        },
        hasMoreMessages: {
            type: Boolean,
            default: false
        },
        totalMessageCount: {
            type: Number,
            default: 0
        }
    },
    emits: ['load-more'],
    template: `
        <div
            v-loading="loading"
            class="message-panel"
            ref="scrollContainer"
            @scroll="handleScroll"
        >
            <!-- 空状态 -->
            <el-empty
                v-if="messages.length === 0"
                description="暂无消息"
                :image-size="120"
            >
                <template #description>
                    <el-text type="info">
                        {{ emptyHint }}
                    </el-text>
                </template>
            </el-empty>

            <!-- 加载更多按钮（顶部） -->
            <div v-if="hasMoreMessages" class="load-more-section">
                <el-button
                    type="primary"
                    size="small"
                    plain
                    @click="handleLoadMore"
                    :loading="loadingMore"
                >
                    <template v-if="!loadingMore">
                        ⬆️ 加载更多历史消息 ({{ totalMessageCount - messages.length }} 条)
                    </template>
                    <template v-else>
                        加载中...
                    </template>
                </el-button>
            </div>

            <!-- 消息列表（移除动画，提升性能） -->
            <!--
                【渲染 key 优化】优先使用 msg_id，回退到本地唯一ID
                - msg_id: 服务端消息都有唯一 msg_id（后端统一按 msg_id 标识消息）
                - _local_id: 本地发送消息时生成的唯一ID（格式: timestamp_random）
                - 优势: 完全独立于数组 index，加载更多消息时不会重新渲染
                - 注意: 消息去重在 store.js 处理，这里只负责稳定的渲染 key
            -->
            <div class="message-list">
                <message-item
                    v-for="(msg, index) in messages"
                    :key="msg.msg_id || msg._local_id || (msg.timestamp + '_' + index)"
                    :message="msg"
                    :identity="identity"
                ></message-item>
            </div>

        </div>
    `,
    setup(props, {emit}) {
        const {ref, watch, nextTick, computed} = Vue;

        const scrollContainer = ref(null);
        const loadingMore = ref(false);
        const lastScrollHeight = ref(0); // 记录加载前的滚动高度

        // ===== 空状态提示 =====
        const emptyHint = computed(() => {
            if (props.identity?.role === 'admin') {
                return props.currentVisitorToken
                    ? `暂无与访客 ${props.currentVisitorToken} 的消息`
                    : '请从左侧选择访客开始对话';
            }
            return '暂无消息，发送第一条消息开始对话';
        });

        // ===== 自动滚动到底部（防抖优化） =====
        let scrollTimer = null;
        const scrollToBottom = () => {
            // 防抖：避免频繁触发滚动
            if (scrollTimer) clearTimeout(scrollTimer);
            scrollTimer = setTimeout(() => {
                nextTick(() => {
                    if (scrollContainer.value) {
                        scrollContainer.value.scrollTop = scrollContainer.value.scrollHeight;
                    }
                });
            }, 50); // 延迟 50ms，批量处理
        };

        // ===== 滚动到顶部检测（自动加载更多） =====
        const handleScroll = (event) => {
            if (!props.hasMoreMessages || loadingMore.value) return;

            const container = event.target;
            const scrollTop = container.scrollTop;

            // 滚动到顶部 50px 以内时自动加载
            if (scrollTop < 50) {
                handleLoadMore();
            }
        };

        // ===== 手动加载更多 =====
        const handleLoadMore = () => {
            if (loadingMore.value || !props.hasMoreMessages) return;

            loadingMore.value = true;

            // 记录加载前的滚动高度
            if (scrollContainer.value) {
                lastScrollHeight.value = scrollContainer.value.scrollHeight;
            }

            // 触发父组件加载
            emit('load-more');

            // 延迟重置 loading 状态
            setTimeout(() => {
                loadingMore.value = false;

                // 保持滚动位置（避免跳动）
                nextTick(() => {
                    if (scrollContainer.value) {
                        const newScrollHeight = scrollContainer.value.scrollHeight;
                        const heightDiff = newScrollHeight - lastScrollHeight.value;
                        scrollContainer.value.scrollTop = heightDiff;
                    }
                });
            }, 300);
        };

        // 监听消息变化，自动滚动（只监听长度变化，避免过度触发）
        watch(
            () => props.messages.length,
            (newLength, oldLength) => {
                // 只有新增消息时才滚动到底部
                if (newLength > oldLength && !loadingMore.value) {
                    scrollToBottom();
                }
            }
        );

        return {
            scrollContainer,
            emptyHint,
            loadingMore,
            handleScroll,
            handleLoadMore
        };
    }
};
