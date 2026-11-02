/**
 * 头部状态栏组件
 * 显示连接状态、客户端ID、身份信息
 */
window.ChatHeader = {
    name: 'ChatHeader',
    props: {
        status: {
            type: String,
            default: '等待连接...'
        },
        clientId: {
            type: String,
            default: ''
        },
        identity: {
            type: Object,
            default: null
        },
        wsUrl: {
            type: String,
            default: ''
        }
    },
    template: `
        <div class="chat-header">
            <h2>客服系统在线沟通面板</h2>

            <el-space :size="15">
                <el-tag :type="statusType" effect="dark">
                    {{ status }}
                </el-tag>

                <el-text size="small" type="info">
                    <strong>客户端ID:</strong> {{ clientId || '未分配' }}
                </el-text>

                <el-text v-if="identity" size="small" type="success">
                    <strong>身份:</strong> {{ identityLabel }}
                </el-text>

                <el-popover
                    placement="bottom"
                    :width="400"
                    trigger="hover"
                    content="WebSocket连接信息"
                >
                    <template #reference>
                        <span style="cursor: pointer; font-size: 18px;">ℹ️</span>
                    </template>
                    <div>
                        <p><strong>连接地址:</strong> {{ wsUrl }}</p>
                        <p v-if="identity?.role === 'admin'">
                            <strong>客服ID:</strong> {{ identity.admin_id }}
                        </p>
                        <p v-if="identity?.role === 'admin'">
                            <strong>昵称:</strong> {{ identity.nickname || '未设置' }}
                        </p>
                        <p v-if="identity?.role === 'visitor'">
                            <strong>访客Token:</strong> {{ identity.token }}
                        </p>
                    </div>
                </el-popover>
            </el-space>
        </div>
    `,
    setup(props) {
        const {computed} = Vue;

        // 状态标签类型
        const statusType = computed(() => {
            if (props.status?.includes('已连接') || props.status?.includes('🟢')) {
                return 'success';
            }
            if (props.status?.includes('连接中') || props.status?.includes('等待')) {
                return 'warning';
            }
            if (props.status?.includes('关闭') || props.status?.includes('🔴')) {
                return 'danger';
            }
            return 'info';
        });

        // 身份标签
        const identityLabel = computed(() => {
            if (!props.identity) return '';

            if (props.identity.role === 'admin') {
                const nickname = props.identity.nickname || '';
                return nickname
                    ? `${nickname} (客服#${props.identity.admin_id})`
                    : `客服#${props.identity.admin_id}`;
            }

            if (props.identity.role === 'visitor') {
                const nickname = props.identity.nickname || '';
                const token = props.identity.token || '';
                return nickname
                    ? `${nickname} (访客)`
                    : `访客(${token.substring(0, 8)}...)`;
            }

            return '';
        });

        return {
            statusType,
            identityLabel
        };
    }
};
