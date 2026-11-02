/**
 * 客服操作栏组件
 * 提供接入/挂断按钮和状态显示
 */
window.AdminActions = {
    name: 'AdminActions',
    props: {
        holdState: {
            type: Object,
            default: () => ({
                holdStatus: 0,
                visitorToken: '',
                adminId: null,
                adminNickname: ''
            })
        },
        currentToken: {
            type: String,
            default: ''
        },
        pending: {
            type: Boolean,
            default: false
        },
        visitors: {
            type: Array,
            default: () => []
        },
        servedVisitors: {
            type: Array,
            default: () => []
        }
    },
    emits: ['connect', 'release'],
    template: `
        <div class="admin-actions-bar">
            <div>
                <el-button-group>
                    <el-button
                        type="success"
                        :disabled="connectDisabled"
                        :loading="pending"
                        @click="$emit('connect')"
                        size="large"
                    >
                        接入访客
                    </el-button>

                    <el-button
                        type="danger"
                        :disabled="releaseDisabled"
                        :loading="pending"
                        @click="$emit('release')"
                        size="large"
                    >
                        挂断会话
                    </el-button>
                </el-button-group>
            </div>

            <div>
                <!-- 接入状态提示 -->
                <el-tag v-if="holdState.visitorToken" type="success" size="large" effect="dark">
                    ✅ 已接入访客: {{ holdState.visitorToken }}
                </el-tag>

                <el-tag v-else type="info" size="large" effect="plain">
                    ⛔ 未接入访客
                </el-tag>
            </div>
        </div>
    `,
    setup(props) {
        const { computed } = Vue;

        // ===== 接入按钮禁用判断 =====
        const connectDisabled = computed(() => {
            // 正在处理中，避免重复提交
            if (props.pending) return true;

            // 未选中访客
            if (!props.currentToken) return true;

            // 其他判断交给后端处理（离线、已被接入等）
            return false;
        });

        // ===== 挂断按钮禁用判断 =====
        const releaseDisabled = computed(() => {
            // 正在处理中，避免重复提交
            if (props.pending) return true;

            // 已接入列表为空，不能点击
            if (!props.servedVisitors || props.servedVisitors.length === 0) {
                return true;
            }

            // 已接入列表有访客，可以点击
            return false;
        });

        return {
            connectDisabled,
            releaseDisabled
        };
    }
};
