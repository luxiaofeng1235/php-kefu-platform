/**
 * 已接入访客卡片组件
 * 显示客服已接入的单个访客信息，支持延长/挂断操作
 */
window.ServedCard = {
    name: 'ServedCard',
    props: {
        visitor: {
            type: Object,
            required: true
        },
        active: {
            type: Boolean,
            default: false
        }
    },
    emits: ['click', 'hangup'],
    template: `
        <el-card
            :class="{ 'served-card': true, 'active': active }"
            shadow="hover"
            @click="$emit('click')"
        >
            <div style="display: flex; justify-content: space-between; align-items: center;">
                <div>
                    <el-text tag="strong" size="large">
                        {{ displayName }}
                    </el-text>
                    <br>
                    <el-text size="small" type="info">
                        🆔 {{ visitor.token }}
                    </el-text>
                    <br>
                    <el-text size="small" type="info">
                        🌐 {{ visitor.ip || '未知' }}{{ visitor.location ? ' (' + visitor.location + ')' : '' }}
                    </el-text>
                </div>

                <el-button
                    size="small"
                    type="danger"
                    @click.stop="$emit('hangup')"
                >
                    挂断
                </el-button>
            </div>
        </el-card>
    `,
    setup(props) {
        const { computed } = Vue;

        // ===== 显示名称 =====
        const displayName = computed(() => {
            return props.visitor.nickname || props.visitor.token || '未知访客';
        });

        return {
            displayName
        };
    }
};
