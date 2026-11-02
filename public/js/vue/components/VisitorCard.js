/**
 * 访客卡片组件
 * 显示单个访客的详细信息（昵称、状态、未读数、停留时长等）
 */
window.VisitorCard = {
    name: 'VisitorCard',
    props: {
        visitor: {
            type: Object,
            required: true
        },
        active: {
            type: Boolean,
            default: false
        },
        warningThreshold: {
            type: Number,
            default: 15000  // 默认15秒
        }
    },
    emits: ['click'],
    template: `
        <div
            :class="cardClasses"
            :data-token="visitor.token"
            @click="$emit('click')"
        >
            <!-- 访客头部信息 -->
            <div class="visitor-header">
                <el-text :type="statusType" size="large" tag="strong">
                    {{ displayName }}
                </el-text>

                <el-tag :type="statusTagType" size="small" effect="dark">
                    {{ statusText }}
                </el-tag>

                <!-- 未读消息数：大号、醒目显示 -->
                <el-badge
                    v-if="visitor.unread_count > 0"
                    :value="visitor.unread_count > 99 ? '99+' : visitor.unread_count"
                    :max="99"
                    :class="['unread-badge', unreadBadgeClass]"
                    type="danger"
                />
            </div>

            <!-- 新消息提示（未读数 > 0 时高亮显示） -->
            <div v-if="visitor.unread_count > 0 && lastMessage" class="new-message-alert">
                <el-text type="danger" size="small" tag="strong">
                    📣 新消息：{{ lastMessage }}
                </el-text>
            </div>

            <!-- 消息预览（未读数 = 0 时显示普通预览） -->
            <el-text v-else-if="lastMessage" class="message-preview" truncated size="small">
                💬 {{ lastMessage }}
            </el-text>

            <!-- 访客元信息 -->
            <div class="visitor-meta">
                <el-text size="small" type="info">
                    🆔 {{ visitor.token }}
                </el-text>
                <el-text size="small" type="info">
                    🌐 IP: {{ visitor.ip || '未知' }}{{ visitor.location ? ' (' + visitor.location + ')' : '' }}
                </el-text>
            </div>

            <!-- 访问和上线时间（同一行） -->
            <div style="margin-top: 8px; display: flex; gap: 12px; flex-wrap: wrap;">
                <el-text size="small" type="info">
                    👤 {{ visitLabel }}
                </el-text>
                <el-text size="small" type="info">
                    🕐 上线: {{ connectedTime }}
                </el-text>
            </div>

            <!-- 停留时长（单独一行） -->
            <div style="margin-top: 4px;">
                <el-text size="small" type="info">
                    ⏱️ {{ stayDuration }}
                </el-text>
            </div>

            <!-- 当前浏览页面 -->
            <div v-if="currentPage" style="margin-top: 8px;">
                <el-tag type="primary" size="small" effect="plain">
                    📄 {{ currentPage }}
                </el-tag>
            </div>

            <!-- 接入状态标签 -->
            <el-tag
                :type="serveStatusType"
                size="small"
                class="serve-status"
                effect="plain"
            >
                {{ serveStatus }}
            </el-tag>

            <!-- 超时警告 -->
            <el-alert
                v-if="isWarning"
                title="⚠️ 访客等待超时！"
                type="warning"
                :closable="false"
                class="warning-alert"
                show-icon
            />
        </div>
    `,
    setup(props) {
        const { computed, onMounted, onUnmounted, watch, ref } = Vue;

        // ===== 使用共享时间源（优化：避免每个卡片创建独立定时器） =====
        const SharedClock = window.VueApp?.SharedClock;
        const now = SharedClock ? SharedClock.getNow() : Vue.ref(Date.now());

        onMounted(() => {
            if (SharedClock) SharedClock.subscribe();
        });

        onUnmounted(() => {
            if (SharedClock) SharedClock.unsubscribe();
        });

        // ===== 显示名称 =====
        const displayName = computed(() => {
            return props.visitor.nickname || props.visitor.token || '未知访客';
        });

        // ===== 在线状态 =====
        const isOnline = computed(() => Number(props.visitor.is_online) === 1);
        const statusType = computed(() => isOnline.value ? 'success' : 'info');
        const statusText = computed(() => isOnline.value ? '在线' : '离线');
        const statusTagType = computed(() => isOnline.value ? 'success' : 'info');

        // ===== 卡片样式 =====
        const cardClasses = computed(() => ({
            'visitor-card': true,
            'active': props.active,
            'online': isOnline.value,
            'offline': !isOnline.value,
            'warning': isWarning.value,
            'waiting-attention': waitingLevel.value === 'attention',
            'waiting-warning': waitingLevel.value === 'warning',
            'waiting-critical': waitingLevel.value === 'critical',
            'has-unread': (props.visitor.unread_count || 0) > 0,
            'has-many-unread': (props.visitor.unread_count || 0) > 3
        }));

        const unreadBadgeClass = computed(() => {
            const count = props.visitor.unread_count || 0;
            if (count > 5) return 'unread-critical';
            if (count > 3) return 'unread-many';
            return 'unread-normal';
        });

        // ===== 超时检测 =====
        const isWarning = computed(() => {
            if (!props.visitor.first_message_at) return false;
            if (props.visitor.served_by_admin) return false;
            if (!isOnline.value) return false;

            const firstMessageTime = new Date(props.visitor.first_message_at).getTime();
            const elapsed = now.value - firstMessageTime;
            return elapsed >= props.warningThreshold;
        });

        const waitingLevel = computed(() => {
            if (!props.visitor.first_message_at) return 'normal';
            if (props.visitor.served_by_admin) return 'normal';
            if (!isOnline.value) return 'normal';

            const firstMessageTime = new Date(props.visitor.first_message_at).getTime();
            const elapsed = now.value - firstMessageTime;

            if (elapsed < 10000) return 'normal';
            if (elapsed < 15000) return 'attention';
            if (elapsed < 30000) return 'warning';
            return 'critical';
        });

        const visitLabel = computed(() => {
            const count = props.visitor.visit_count || 1;
            return count > 1 ? `第${count}次访问` : '首次访问';
        });

        const connectedTime = computed(() => {
            if (!props.visitor.connected_at) return '未知';
            let timestamp = props.visitor.connected_at;
            if (timestamp < 10000000000) timestamp = timestamp * 1000;
            const date = new Date(timestamp);
            return date.toLocaleString('zh-CN', {hour12:false});
        });

        // ===== 停留时长 (Modified for VueApp.Tools) =====
        const stayDuration = computed(() => {
            // 依赖 now.value 来触发每秒更新（仅在线时）
            const _trigger = now.value;
            const tools = window.VueApp?.Tools;
            if (tools && tools.formatStayDuration) {
                // 离线时：使用 disconnected_at 或 last_activity_at 计算最终停留时长
                if (!isOnline.value) {
                    const endTime = props.visitor.disconnected_at || props.visitor.last_activity_at;
                    if (endTime && props.visitor.connected_at) {
                        return tools.formatStayDurationBetween(props.visitor.connected_at, endTime);
                    }
                }
                // 在线时：实时计算
                return tools.formatStayDuration(props.visitor.connected_at);
            }
            return '计算中...';
        });

        const lastMessage = computed(() => {
            // 从前端 Store 读取最近一条消息
            if (window.VueApp?.Store?.conversations) {
                const messages = window.VueApp.Store.conversations[props.visitor.token];
                if (messages && messages.length > 0) {
                    // 取最近（最后）一条消息
                    const lastMsg = messages[messages.length - 1];
                    let content = lastMsg.content || '';

                    // ✅ 使用公用函数解析 AI 消息 JSON 格式
                    const tools = window.VueApp?.Tools;
                    if (tools && tools.parseAIMessageContent) {
                        content = tools.parseAIMessageContent(content);
                    }

                    // 截断长度，最多显示 30 字符
                    return content.length > 30 ? content.substring(0, 30) + '...' : content;
                }
            }

            // 没有消息，返回空字符串（不渲染）
            return '';
        });

        const serveStatus = computed(() => {
            const adminId = props.visitor.served_by_admin;
            if (!adminId) return '待接入';
            const nickname = props.visitor.served_by_admin_nickname || '';
            return nickname ? `客服 ${nickname}(ID=${adminId}) 已接入` : `客服 ID=${adminId} 已接入`;
        });

        const serveStatusType = computed(() => props.visitor.served_by_admin ? 'success' : 'warning');

        const currentPage = computed(() => {
            const title = props.visitor.current_page_title;
            const path = props.visitor.current_page_path;
            if (title) return title.length > 20 ? title.substring(0, 20) + '...' : title;
            if (path) return path.length > 25 ? path.substring(0, 25) + '...' : path;
            return null;
        });

        return {
            displayName, statusType, statusText, statusTagType, cardClasses, unreadBadgeClass,
            isWarning, waitingLevel, visitLabel, connectedTime, stayDuration, lastMessage,
            serveStatus, serveStatusType, currentPage
        };
    }
};