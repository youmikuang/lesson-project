# 数据库结构

## 1. courses 课程表

| 字段             | 类型     | 说明                                  |
|------------------|----------|---------------------------------------|
| id               | bigint   | 主键                                  |
| name             | varchar  | 课程名称                              |
| description      | text     | 课程描述                              |
| capacity         | int      | 容量上限，默认30人                    |
| scheduled_at     | datetime | 课程时间                              |
| duration_minutes | int      | 课程时长（分钟）                      |
| status           | enum     | 状态：open/closed/cancelled/completed |
| timestamps       | -        | 创建/更新时间                         |

索引设计：
- scheduled_at - 按时间查询
- status - 按状态筛选
- (status, scheduled_at) - 复合索引用于查询"开放的未来课程"

## 2. reservations 预约记录表

| 字段              | 类型      | 说明                                                  |
  |-------------------|-----------|-------------------------------------------------------|
| id                | bigint    | 主键                                                  |
| user_id           | bigint    | 用户ID（外键）                                        |
| course_id         | bigint    | 课程ID（外键）                                        |
| status            | enum      | confirmed=已确认 / waitlisted=候补 / cancelled=已取消 |
| waitlist_position | int       | 候补位置（排序用）                                    |
| reserved_at       | timestamp | 预约时间                                              |
| cancelled_at      | timestamp | 取消时间                                              |
| timestamps        | -         | 创建/更新时间                                         |

索引设计：
- (user_id, course_id, status) - 唯一约束，配合应用层防止重复预约
- (course_id, status) - 查询某课程的预约
- (user_id, status) - 查询某用户的预约
- (course_id, status, waitlist_position) - 候补名单排序

业务规则实现方式

| 规则         | 实现方式                                   |
|--------------|--------------------------------------------|
| 容量上限     | courses.capacity 字段                      |
| 候补名单     | status=waitlisted + waitlist_position 排序 |
| 自动递补     | 应用层监听取消事件，更新候补第一位状态     |
| 防止重复预约 | 唯一索引 + 应用层检查非cancelled状态       |
