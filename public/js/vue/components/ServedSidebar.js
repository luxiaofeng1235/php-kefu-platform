/**
 * 已接入访客列表侧边栏组件
 * 显示当前客服已接入的所有访客
 */
window.ServedSidebar = {
    name: 'ServedSidebar',
    components: {
        ServedCard: window.ServedCard
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
        loading: { // 新增 loading 属性
            type: Boolean,
            default: false
        }
    },
    emits: ['select', 'hangup'],
    template: `
        <div class="served-sidebar" style="width: 300px;" v-loading="loading">
            <!-- 侧边栏头部 -->
            <div class="sidebar-header">
                <h3>
                    我的接入列表
                </h3>

                <el-badge
                    :value="visitors.length"
                    :max="99"
                    class="count-badge"
                    type="primary"
                />
            </div>

            <!-- 已接入列表容器 -->
            <div class="served-list-container">
                <!-- 空状态 -->
                <el-empty
                    v-if="visitors.length === 0"
                    description="暂无接入的访客"
                    :image-size="100"
                />

                <!-- 已接入列表 -->
                <div class="served-list">
                    <served-card
                        v-for="visitor in visitors"
                        :key="visitor.token"
                        :visitor="visitor"
                        :active="visitor.token === currentToken"
                        @click="$emit('select', visitor.token)"
                        @hangup="$emit('hangup', visitor.token)"
                        @extend="$emit('extend', visitor.token)"
                    ></served-card>
                </div>
            </div>
        </div>
    `
};
