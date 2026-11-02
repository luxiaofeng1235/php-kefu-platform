/**
 * 消息输入框组件
 * 支持快捷回复、发送限制、AI 消息类型选择
 */
window.MessageInput = {
    name: 'MessageInput',
    props: {
        modelValue: {
            type: String,
            default: ''
        },
        identity: {
            type: Object,
            default: null
        },
        currentVisitorToken: {
            type: String,
            default: ''
        },
        servedTokens: {
            type: Array,
            default: () => []
        },
        targetAdminId: {
            type: Number,
            default: null
        },
        quickReplies: {
            type: Array,
            default: () => []
        }
    },
    emits: ['update:modelValue', 'send', 'typing'],
    template: `
        <div class="message-input-container">
            <!-- 快捷回复（仅客服端） -->
            <el-select
                v-if="identity?.role === 'admin' && quickReplies.length > 0"
                v-model="selectedQuickReply"
                placeholder="💬 选择快捷回复 (Alt+1~9)"
                @change="handleQuickReply"
                class="quick-reply-select"
                clearable
            >
                <el-option
                    v-for="(reply, index) in quickReplies"
                    :key="index"
                    :label="'[' + (index + 1) + '] ' + reply"
                    :value="reply"
                >
                    <span style="float: left">{{ reply }}</span>
                    <span style="float: right; color: #8492a6; font-size: 12px">Alt+{{ index + 1 }}</span>
                </el-option>
            </el-select>

            <!-- 输入框和发送按钮 -->
            <div class="input-row">
                <el-input
                    :model-value="modelValue"
                    @update:model-value="handleModelUpdate"
                    type="textarea"
                    :rows="3"
                    placeholder="输入消息内容，Enter 发送，Shift+Enter 换行"
                    :disabled="!canSend"
                    @keydown.enter.exact.prevent="handleSend"
                    maxlength="2000"
                    show-word-limit
                />

                <el-button
                    type="primary"
                    :disabled="!canSend || !modelValue?.trim()"
                    @click="handleSend"
                    :loading="sending"
                    size="large"
                >
                    发送
                </el-button>
            </div>

            <!-- 访客端：消息类型选择 -->
            <div v-if="identity?.role === 'visitor'" class="visitor-options">
                <!-- AI 消息模拟（测试用） -->
                <div style="display: inline-flex; align-items: center; gap: 12px;">
                    <el-text size="small" type="info" tag="strong">消息类型：</el-text>
                    <el-radio-group v-model="aiRoleSelect" size="default">
                        <el-radio-button label="">普通消息</el-radio-button>
                        <el-radio-button label="user">🧑 AI用户</el-radio-button>
                        <el-radio-button label="assistant">🤖 AI助手</el-radio-button>
                    </el-radio-group>
                    <el-tag v-if="aiRoleSelect" type="warning" effect="dark" size="small">
                        测试模式：将包装为 AI 格式
                    </el-tag>
                </div>

                <el-divider direction="vertical" />

                <!-- 提示信息 -->
                <el-text size="small" type="success">
                    💬 消息将自动发送给所有客服（在线即时接收，离线自动留言）
                </el-text>
            </div>

            <!-- 客服端：发送提示 -->
            <div v-if="identity?.role === 'admin'" class="admin-hint">
                <el-text size="small" :type="canSend ? 'success' : 'warning'">
                    {{ sendHint }}
                </el-text>
            </div>
        </div>
    `,
    setup(props, { emit }) {
        const { ref, computed, watch, onMounted, onUnmounted } = Vue;
        const { ElMessage } = ElementPlus;

        const sending = ref(false);
        const selectedQuickReply = ref('');
        const aiRoleSelect = ref('');  // AI 消息模拟选择

        // ===== 是否可以发送 =====
        const canSend = computed(() => {
            if (!props.identity) return false;

            if (props.identity.role === 'admin') {
                // 客服必须选中已接入的访客
                if (!props.currentVisitorToken) return false;
                return props.servedTokens.includes(props.currentVisitorToken);
            }

            // 访客始终可发送
            return true;
        });

        // ===== 发送提示 =====
        const sendHint = computed(() => {
            if (props.identity?.role !== 'admin') return '';

            if (!props.currentVisitorToken) {
                return '⚠️ 请先选择访客';
            }

            if (!props.servedTokens.includes(props.currentVisitorToken)) {
                return '⚠️ 请先接入该访客后再发送消息';
            }

            return `✅ 正在与访客 ${props.currentVisitorToken} 对话`;
        });

        const TYPING_IDLE_MS = 1600;
        const TYPING_REFRESH_MS = 2500;
        let typingIdleTimer = null;
        let typingRefreshTimer = null;
        let activeTypingToken = '';

        const emitTypingStatus = (visitorToken, status) => {
            if (visitorToken) {
                emit('typing', { visitor_token: visitorToken, status });
            }
        };

        const stopTyping = () => {
            if (typingIdleTimer) clearTimeout(typingIdleTimer);
            if (typingRefreshTimer) clearInterval(typingRefreshTimer);
            typingIdleTimer = null;
            typingRefreshTimer = null;

            if (activeTypingToken) {
                emitTypingStatus(activeTypingToken, false);
                activeTypingToken = '';
            }
        };

        const handleModelUpdate = (value) => {
            emit('update:modelValue', value);

            if (props.identity?.role !== 'admin' || !canSend.value || !String(value || '').trim()) {
                stopTyping();
                return;
            }

            const visitorToken = props.currentVisitorToken;
            if (activeTypingToken && activeTypingToken !== visitorToken) {
                stopTyping();
            }

            if (!activeTypingToken) {
                activeTypingToken = visitorToken;
                emitTypingStatus(activeTypingToken, true);
                typingRefreshTimer = setInterval(() => {
                    if (activeTypingToken === visitorToken && canSend.value && props.currentVisitorToken === visitorToken) {
                        emitTypingStatus(visitorToken, true);
                    } else {
                        stopTyping();
                    }
                }, TYPING_REFRESH_MS);
            }

            if (typingIdleTimer) clearTimeout(typingIdleTimer);
            typingIdleTimer = setTimeout(stopTyping, TYPING_IDLE_MS);
        };

        watch(() => props.currentVisitorToken, (visitorToken) => {
            if (activeTypingToken && activeTypingToken !== visitorToken) {
                stopTyping();
            }
        });

        watch(canSend, (allowed) => {
            if (!allowed) stopTyping();
        });

        // ===== 构造发送内容（支持 AI 消息格式） =====
        const buildMessageContent = (rawContent) => {
            // 如果选择了 AI 角色，包装成 AI 消息格式
            if (aiRoleSelect.value && props.identity?.role === 'visitor') {
                return JSON.stringify({
                    role: aiRoleSelect.value,
                    content: rawContent
                });
            }
            return rawContent;
        };

        // ===== 发送消息 =====
        const handleSend = async () => {
            if (!canSend.value || !props.modelValue?.trim()) {
                return;
            }

            if (sending.value) return;

            stopTyping();
            sending.value = true;

            try {
                // 构造消息内容（如果选择了 AI 角色，会包装成 JSON 格式）
                const finalContent = buildMessageContent(props.modelValue.trim());
                emit('send', finalContent);
            } finally {
                // 延迟重置，避免快速连击
                setTimeout(() => {
                    sending.value = false;
                }, 300);
            }
        };

        // ===== 快捷回复 =====
        const handleQuickReply = (value) => {
            if (!value) return;

            emit('update:modelValue', value);

            // 清空选择
            setTimeout(() => {
                selectedQuickReply.value = '';
            }, 100);
        };

        // ===== 快捷键监听（Alt+1~9） =====
        const handleKeyDown = (event) => {
            // 只在客服端且有快捷回复时生效
            if (props.identity?.role !== 'admin' || !props.quickReplies || props.quickReplies.length === 0) {
                return;
            }

            // 检测 Alt + 数字键（1-9）
            if (event.altKey && !event.ctrlKey && !event.shiftKey && !event.metaKey) {
                const key = event.key;
                const num = parseInt(key);

                if (!isNaN(num) && num >= 1 && num <= 9) {
                    const index = num - 1;

                    if (index < props.quickReplies.length) {
                        event.preventDefault(); // 阻止默认行为

                        const reply = props.quickReplies[index];
                        emit('update:modelValue', reply);

                        // 自动发送（可选：如果想按快捷键后立即发送）
                        if (canSend.value) {
                            setTimeout(() => {
                                handleSend();
                            }, 100);
                        }

                        console.log(`🎯 快捷键触发: Alt+${num} -> "${reply}"`);
                    }
                }
            }
        };

        // 组件挂载时添加全局键盘监听
        onMounted(() => {
            window.addEventListener('keydown', handleKeyDown);
            console.log('✅ 快捷回复快捷键已启用 (Alt+1~9)');
        });

        // 组件卸载时移除监听
        onUnmounted(() => {
            window.removeEventListener('keydown', handleKeyDown);
            stopTyping();
        });

        return {
            canSend,
            sending,
            sendHint,
            selectedQuickReply,
            aiRoleSelect,
            handleModelUpdate,
            handleSend,
            handleQuickReply
        };
    }
};
