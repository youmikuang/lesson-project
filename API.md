# 课程预约系统 API 文档

## 通用说明

### 基础信息
- **Base URL**: `/api`
- **数据格式**: JSON
- **字符编码**: UTF-8

### 认证方式
需要认证的接口使用 Laravel Sanctum Token 认证：
```
Authorization: Bearer {token}
```

### 统一响应格式

#### 成功响应
```json
{
    "success": true,
    "message": "操作成功",
    "data": { ... }
}
```

#### 错误响应
```json
{
    "success": false,
    "message": "错误描述"
}
```

### HTTP 状态码
| 状态码 | 说明 |
|--------|------|
| 200 | 请求成功 |
| 201 | 创建成功 |
| 400 | 请求参数错误 |
| 401 | 未认证 |
| 403 | 无权限 |
| 404 | 资源不存在 |
| 409 | 资源冲突（如重复预约） |
| 500 | 服务器内部错误 |

---

## API 1: 预约课程

### 接口信息
- **URL**: `POST /api/courses/{course_id}/reservations`
- **认证**: 需要认证
- **描述**: 用户预约指定课程，支持满员时自动加入候补名单

### 路径参数
| 参数 | 类型 | 必填 | 说明 |
|------|------|------|------|
| course_id | integer | 是 | 课程ID |

### 请求示例
```bash
curl -X POST http://localhost/api/courses/1/reservations \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json"
```

### 成功响应

#### 预约成功（有空位）
**HTTP Status: 201**
```json
{
    "success": true,
    "message": "预约成功",
    "data": {
        "reservation_id": 1,
        "course_id": 1,
        "course_name": "瑜伽初级班",
        "status": "confirmed",
        "status_text": "已确认预约",
        "is_waitlisted": false,
        "remaining_slots": 4
    }
}
```

#### 加入候补名单（课程已满）
**HTTP Status: 201**
```json
{
    "success": true,
    "message": "课程已满，已加入候补名单",
    "data": {
        "reservation_id": 2,
        "course_id": 1,
        "course_name": "瑜伽初级班",
        "status": "waitlisted",
        "status_text": "候补名单中",
        "is_waitlisted": true,
        "waitlist_position": 1
    }
}
```

### 错误响应

#### 课程不可预约
**HTTP Status: 400**
```json
{
    "success": false,
    "message": "该课程当前不可预约"
}
```

#### 已预约过该课程
**HTTP Status: 409**
```json
{
    "success": false,
    "message": "您已经预约过该课程",
    "data": {
        "reservation_id": 1,
        "status": "confirmed",
        "status_text": "已确认预约"
    }
}
```

### 业务逻辑
1. 使用数据库事务保证数据一致性
2. 对课程记录加锁，防止并发超卖
3. 检查课程状态是否为 `open`
4. 检查用户是否已有该课程的有效预约
5. 根据剩余名额决定是确认预约还是加入候补
6. 候补名单按 `waitlist_position` 排序

---

## API 2: 取消预约

### 接口信息
- **URL**: `DELETE /api/reservations/{reservation_id}`
- **认证**: 需要认证
- **描述**: 用户取消自己的预约，如果取消的是已确认预约，自动将候补名单第一位转为确认状态

### 路径参数
| 参数 | 类型 | 必填 | 说明 |
|------|------|------|------|
| reservation_id | integer | 是 | 预约ID |

### 请求示例
```bash
curl -X DELETE http://localhost/api/reservations/1 \
  -H "Authorization: Bearer {token}"
```

### 成功响应

#### 取消成功（无候补递补）
**HTTP Status: 200**
```json
{
    "success": true,
    "message": "预约已取消",
    "data": {
        "reservation_id": 1,
        "course_id": 1,
        "cancelled_at": "2024-01-15T10:30:00.000000Z"
    }
}
```

#### 取消成功（有候补递补）
**HTTP Status: 200**
```json
{
    "success": true,
    "message": "预约已取消",
    "data": {
        "reservation_id": 1,
        "course_id": 1,
        "cancelled_at": "2024-01-15T10:30:00.000000Z",
        "promoted_reservation": {
            "reservation_id": 5,
            "user_id": 3,
            "message": "候补用户已自动递补"
        }
    }
}
```

### 错误响应

#### 无权操作
**HTTP Status: 403**
```json
{
    "success": false,
    "message": "无权操作此预约"
}
```

#### 预约已被取消
**HTTP Status: 400**
```json
{
    "success": false,
    "message": "该预约已被取消"
}
```

#### 预约不存在
**HTTP Status: 404**
```json
{
    "message": "No query results for model [App\\Models\\Reservation] 999"
}
```

### 业务逻辑
1. 验证预约是否属于当前用户
2. 检查预约状态是否已取消
3. 使用数据库事务保证原子性
4. 如果取消的是已确认预约：
   - 查找该课程候补名单中最早的一条记录（按 `waitlist_position` 排序）
   - 将其状态改为 `confirmed`
   - 记录候补递补日志
5. 如果取消的是候补预约：
   - 更新其他候补用户的 `waitlist_position`（前移）
6. 所有操作记录到日志中

---

## API 3: 课程列表查询

### 接口信息
- **URL**: `GET /api/courses`
- **认证**: 不需要认证
- **描述**: 获取课程列表，支持筛选、排序和分页

### 查询参数
| 参数 | 类型 | 必填 | 默认值 | 说明 |
|------|------|------|--------|------|
| start_date | string | 否 | - | 开始日期筛选 (格式: YYYY-MM-DD) |
| end_date | string | 否 | - | 结束日期筛选 (格式: YYYY-MM-DD) |
| available_only | boolean | 否 | false | 只显示有空位的课程 |
| sort_by | string | 否 | scheduled_at | 排序字段 |
| sort_order | string | 否 | asc | 排序方向 (asc/desc) |
| page | integer | 否 | 1 | 页码 |
| per_page | integer | 否 | 15 | 每页数量 (最大100) |

### 排序字段选项
| 字段 | 说明 |
|------|------|
| scheduled_at | 按开始时间排序 |
| name | 按课程名称排序 |
| capacity | 按课程容量排序 |
| remaining_slots | 按剩余名额排序 |
| created_at | 按创建时间排序 |

### 请求示例

#### 获取所有课程
```bash
curl -X GET "http://localhost/api/courses"
```

#### 按日期范围筛选（如查找下周的课程）
```bash
curl -X GET "http://localhost/api/courses?start_date=2024-01-15&end_date=2024-01-21"
```

#### 只显示有空位的课程
```bash
curl -X GET "http://localhost/api/courses?available_only=true"
```

#### 按剩余名额降序排序
```bash
curl -X GET "http://localhost/api/courses?sort_by=remaining_slots&sort_order=desc"
```

#### 分页查询
```bash
curl -X GET "http://localhost/api/courses?page=2&per_page=10"
```

#### 组合查询
```bash
curl -X GET "http://localhost/api/courses?start_date=2024-01-15&end_date=2024-01-21&available_only=true&sort_by=scheduled_at&sort_order=asc&per_page=20"
```

### 成功响应
**HTTP Status: 200**
```json
{
    "success": true,
    "data": [
        {
            "id": 1,
            "name": "瑜伽初级班",
            "description": "适合初学者的瑜伽课程",
            "capacity": 20,
            "scheduled_at": "2024-01-15T09:00:00.000000Z",
            "duration_minutes": 60,
            "status": "open",
            "confirmed_count": 15,
            "waitlisted_count": 3,
            "remaining_slots": 5,
            "is_full": false
        },
        {
            "id": 2,
            "name": "健身操高级班",
            "description": "高强度健身操课程",
            "capacity": 15,
            "scheduled_at": "2024-01-15T14:00:00.000000Z",
            "duration_minutes": 45,
            "status": "open",
            "confirmed_count": 15,
            "waitlisted_count": 5,
            "remaining_slots": 0,
            "is_full": true
        }
    ],
    "meta": {
        "current_page": 1,
        "last_page": 3,
        "per_page": 15,
        "total": 42
    }
}
```

### 响应字段说明
| 字段 | 类型 | 说明 |
|------|------|------|
| id | integer | 课程ID |
| name | string | 课程名称 |
| description | string | 课程描述 |
| capacity | integer | 课程容量（最大人数） |
| scheduled_at | string | 课程开始时间 (ISO 8601格式) |
| duration_minutes | integer | 课程时长（分钟） |
| status | string | 课程状态 |
| confirmed_count | integer | 已确认预约人数 |
| waitlisted_count | integer | 候补名单人数 |
| remaining_slots | integer | 剩余名额 |
| is_full | boolean | 是否已满 |

### 分页信息说明
| 字段 | 类型 | 说明 |
|------|------|------|
| current_page | integer | 当前页码 |
| last_page | integer | 最后一页页码 |
| per_page | integer | 每页数量 |
| total | integer | 总记录数 |

### 业务逻辑
1. 默认只显示状态为 `open` 的课程
2. 使用 Laravel 查询构建器实现动态查询
3. 支持多条件组合筛选
4. 使用 `withCount` 高效统计预约数量
5. 分页数量上限为 100，防止一次请求过多数据

---

## Postman 测试指南

### 环境变量设置
```
base_url: http://localhost/api
token: {your_sanctum_token}
```

### 测试用例

#### 1. 测试课程列表查询
```
GET {{base_url}}/courses
GET {{base_url}}/courses?available_only=true
GET {{base_url}}/courses?start_date=2024-01-15&end_date=2024-01-21
GET {{base_url}}/courses?sort_by=remaining_slots&sort_order=desc
```

#### 2. 测试预约课程
```
POST {{base_url}}/courses/1/reservations
Headers:
  Authorization: Bearer {{token}}
```

#### 3. 测试取消预约
```
DELETE {{base_url}}/reservations/1
Headers:
  Authorization: Bearer {{token}}
```

### 测试场景

#### 场景1: 正常预约流程
1. 查询有空位的课程
2. 预约课程，获得确认状态
3. 取消预约

#### 场景2: 候补名单流程
1. 预约已满的课程，进入候补
2. 其他用户取消确认预约
3. 验证候补用户自动递补

#### 场景3: 异常情况测试
1. 重复预约同一课程 -> 409错误
2. 取消不存在的预约 -> 404错误
3. 取消他人的预约 -> 403错误
4. 未认证访问需认证接口 -> 401错误

---

## 数据库表结构参考

### courses 表
| 字段 | 类型 | 说明 |
|------|------|------|
| id | bigint | 主键 |
| name | varchar | 课程名称 |
| description | text | 课程描述 |
| capacity | int | 课程容量 |
| scheduled_at | datetime | 课程时间 |
| duration_minutes | int | 课程时长 |
| status | varchar | 课程状态 (open/closed) |
| created_at | timestamp | 创建时间 |
| updated_at | timestamp | 更新时间 |

### reservations 表
| 字段 | 类型 | 说明 |
|------|------|------|
| id | bigint | 主键 |
| user_id | bigint | 用户ID |
| course_id | bigint | 课程ID |
| status | varchar | 预约状态 (confirmed/waitlisted/cancelled) |
| waitlist_position | int | 候补位置 |
| reserved_at | datetime | 预约时间 |
| cancelled_at | datetime | 取消时间 |
| created_at | timestamp | 创建时间 |
| updated_at | timestamp | 更新时间 |
