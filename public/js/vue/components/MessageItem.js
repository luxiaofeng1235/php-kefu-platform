/**
 * 消息项组件
 * 显示单条聊天消息（包含元信息、内容、特殊标记）
 */
window.MessageItem = {
    name: 'MessageItem',
    props: {
        message: {
            type: Object,
            required: true
        },
        identity: {
            type: Object,
            default: null
        }
    },
    template: `
        <div :class="messageClasses">
            <!-- 消息元信息 -->
            <div class="message-meta">
                <!-- AI 角色标签 -->
                <el-tag v-if="aiRoleLabel" :type="aiRoleType" size="small" effect="dark">
                    {{ aiRoleLabel }}
                </el-tag>

                <!-- 场景标签 -->
                <el-tag v-if="message.scene === 'ai_assist'" type="warning" size="small" effect="dark">
                    AI消息
                </el-tag>

                <!-- 待发送标记 (离线队列) -->
                <el-tag v-if="message.is_offline" type="danger" size="small" effect="dark">
                    ⏳ 等待网络...
                </el-tag>

                <el-tag v-if="message.offline" type="info" size="small" effect="plain">
                    离线补发
                </el-tag>

                <el-tag v-if="message.broadcast" type="success" size="small" effect="plain">
                    客服广播
                </el-tag>

                <!-- 发送方 -> 接收方 -->
                <el-text size="small" type="info">
                    {{ fromLabel }} → {{ toLabel }}
                </el-text>

                <!-- 时间 -->
                <el-text size="small" type="info">
                    {{ formatTime }}
                </el-text>
            </div>

            <!-- 消息内容 -->
            <div class="message-content">
                <el-text>{{ displayContent }}</el-text>

                <!-- 提示信息 -->
                <div v-if="hints" class="message-hints">
                    <el-text size="small" type="warning">
                        ⚠️ {{ hints }}
                    </el-text>
                </div>
            </div>
        </div>
    `,
    setup(props) {
        const { computed } = Vue;

        // ===== 使用公用工具函数 =====
        const tools = window.VueApp?.Tools;

        // ===== 判断是否为 AI 消息 JSON 格式 =====
        const isAIMessageJSON = (value) => {
            return tools && tools.isAIMessageJSON ? tools.isAIMessageJSON(value) : false;
        };

        // ===== 解析消息内容（处理 AI 消息 JSON 格式） =====
        const displayContent = computed(() => {
            const rawContent = props.message.content;

            // 使用公用函数解析
            if (tools && tools.parseAIMessageContent) {
                return tools.parseAIMessageContent(rawContent);
            }

            // 降级方案：直接返回原内容
            return rawContent;
        });

        // ===== 是否自己发送的消息 =====
        const isSelf = computed(() => !!props.message.is_me || !!props.message.is_self);

        // ===== 消息样式 =====
        const messageClasses = computed(() => ({
            'message-item': true,
            'message-self': isSelf.value,
            'message-other': !isSelf.value
        }));

        // ===== 发送方标签 =====
        const fromLabel = computed(() => {
            const from = props.message.from || {};

            if (from.role === 'admin') {
                const adminId = from.admin_id || '?';
                const nickname = from.nickname || '';
                return nickname ? `${nickname}(客服#${adminId})` : `客服#${adminId}`;
            }

            if (from.role === 'visitor') {
                const token = from.token || from.visitor_token;
                if (token && token !== '?') {
                    const shortToken = token.length > 12 ? `${token.substring(0, 8)}...` : token;
                    return `访客(${shortToken})`;
                }

                // 兜底：如果是自己发的消息，使用 identity
                if (props.message.is_me && props.identity && props.identity.token) {
                    const token = props.identity.token;
                    const shortToken = token.length > 12 ? `${token.substring(0, 8)}...` : token;
                    return `访客(${shortToken})`;
                }

                return '访客';
            }

            // 处理本地存储恢复的 'other' 角色
            if (from.role === 'other') {
                if (props.identity?.role === 'admin') {
                    const token = from.token || props.message.visitor_token;
                    if (token) {
                        const shortToken = token.length > 12 ? `${token.substring(0, 8)}...` : token;
                        return `访客(${shortToken})`;
                    }
                    return '访客';
                }
                // 访客端看对方 -> 客服
                if (props.identity?.role === 'visitor') {
                    return '客服';
                }
            }

            // 本地乐观更新的消息可能没有详细的 from 结构
            if (props.message.is_me && props.identity) {
                 if (props.identity.role === 'admin') {
                     return props.identity.nickname || '我(客服)';
                 } else {
                     const token = props.identity.token;
                     if (token) {
                         const shortToken = token.length > 12 ? `${token.substring(0, 8)}...` : token;
                         return `访客(${shortToken})`;
                     }
                     return '访客(我)';
                 }
            }

            return '未知';
        });

        // ===== 接收方标签 =====
        const toLabel = computed(() => {
            const from = props.message.from || {};

            // 【新修复】访客端接收客服消息（包括 'admin' 和 'other'），接收方总是显示当前访客的 token
            if (props.identity?.role === 'visitor' && !isSelf.value) {
                if (from.role === 'admin' || from.role === 'other') {
                    return props.identity.token; // 直接显示当前访客的 token
                }
            }

            // 广播优先
            if (props.message.broadcast) {
                // 若有目标列表则展示具体客服
                const rawTargets = Array.isArray(props.message.targets)
                    ? props.message.targets
                    : (Array.isArray(props.message.target_admin_ids) ? props.message.target_admin_ids : []);
                const targets = Array.isArray(rawTargets) ? rawTargets : [];
                const parsedTargets = targets.length === 0 && typeof rawTargets === 'string'
                    ? rawTargets.split(',').map(s => s.trim()).filter(Boolean)
                    : targets.map(id => String(id)).filter(Boolean);
                if (parsedTargets.length > 0) {
                    return `客服#${parsedTargets.join('、#')}`;
                }
                return '客服广播';
            }

            // 目标列表（非 broadcast 场景）
            const targets = Array.isArray(props.message.targets)
                ? props.message.targets
                : (Array.isArray(props.message.target_admin_ids) ? props.message.target_admin_ids : []);
            if (targets.length > 0) {
                const ids = targets.map(id => String(id)).filter(Boolean);
                if (ids.length > 0) {
                    return `客服#${ids.join('、#')}`;
                }
            }

            // 显式标记广播但无具体 ID
            if (props.message.broadcast) {
                return '客服广播';
            }

            const to = props.message.to || {};

            if (to.role === 'admin') {
                const adminId = to.admin_id;
                const nickname = to.nickname;

                // 有具体客服信息
                if (adminId) {
                    return nickname ? `${nickname}(客服#${adminId})` : `客服#${adminId}`;
                }

                // 访客端广播消息，没有指定客服
                return '在线客服';
            }

            if (to.role === 'visitor') {
                const token = to.token || to.visitor_token;
                if (token && token !== '?') {
                    const shortToken = token.length > 12 ? `${token.substring(0, 8)}...` : token;
                    return `访客(${shortToken})`;
                }
                return '访客';
            }

            if (props.message.target_token) {
                 const token = props.message.target_token;
                 const shortToken = token.length > 12 ? `${token.substring(0, 8)}...` : token;
                 return `访客(${shortToken})`;
            }

            // 本地存储恢复的消息可能没有 to 字段
            if (isSelf.value && props.identity?.role === 'admin' && props.message.visitor_token) {
                const token = props.message.visitor_token;
                const shortToken = token.length > 12 ? `${token.substring(0, 8)}...` : token;
                return `访客(${shortToken})`;
            }

            // 兜底：如果是访客端的广播消息
            // 上面 broadcast 已处理

            // 【修复】客服发给访客的消息，在访客端显示时，接收方应该是"我"
            if (!isSelf.value && from.role === 'admin' && props.identity?.role === 'visitor') {
                return '访客(我)';
            }

            return '未知';
        });

        // ===== 格式化时间 =====
        const formatTime = computed(() => {
            const timestamp = props.message.timestamp || props.message.time;
            if (!timestamp) return '';

            const tools = window.VueApp?.Tools;
            if (tools && tools.formatTime) {
                return tools.formatTime(timestamp);
            }

            const date = new Date(timestamp * 1000);
            return date.toLocaleString('zh-CN', {
                month: '2-digit', day: '2-digit',
                hour: '2-digit', minute: '2-digit', second: '2-digit',
                hour12: false
            });
        });

        // ===== 提示信息 =====
        const hints = computed(() => {
            const parts = [];
            if (props.message.broadcast &&
                Array.isArray(props.message.offline_message_ids) &&
                props.message.offline_message_ids.length > 0) {
                parts.push('部分客服离线，已自动留言');
            }
            if (props.message.offline && props.message.created_at) {
                parts.push(`留言时间：${props.message.created_at}`);
            }
            return parts.length > 0 ? parts.join('，') : null;
        });

        // ===== 获取 AI 角色（兼容历史消息） =====
        const getAIRole = computed(() => {
            // 1. 优先从 extra.ai_role 读取（新消息、后端返回的）
            if (props.message.extra?.ai_role) {
                return props.message.extra.ai_role;
            }

            // 2. 如果没有 ai_role，尝试从原始 content 解析（兼容历史消息）
            const rawContent = props.message.content;
            if (isAIMessageJSON(rawContent)) {
                try {
                    const parsed = JSON.parse(rawContent.trim());
                    return parsed.role;  // 返回 "user" 或 "assistant"
                } catch (e) {
                    return null;
                }
            }

            return null;
        });

        // ===== AI 角色标签 =====
        const aiRoleLabel = computed(() => {
            const aiRole = getAIRole.value;
            if (aiRole === 'assistant') return '🤖 AI回复';
            if (aiRole === 'user') return '🧑 用户';
            return null;
        });

        // ===== AI 角色标签类型 =====
        const aiRoleType = computed(() => {
            const aiRole = getAIRole.value;
            if (aiRole === 'assistant') return 'success';  // 绿色
            if (aiRole === 'user') return 'primary';       // 蓝色
            return 'info';
        });

        return {
            displayContent,
            messageClasses,
            fromLabel,
            toLabel,
            formatTime,
            hints,
            aiRoleLabel,
            aiRoleType
        };
    }
};
