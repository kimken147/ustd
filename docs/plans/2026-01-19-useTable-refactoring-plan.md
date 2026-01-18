# useTable Refactoring Plan

## Overview

This document outlines the plan to refactor the custom `useTable` hook to use Refine's official patterns. This is part of Task 3.5 from the monorepo upgrade plan.

## Current State

### Custom useTable Locations

- `/apps/admin/src/hooks/useTable.tsx` (31 usages)
- `/apps/merchant/src/hooks/useTable.tsx` (6 usages)

### Custom Table Component Locations

- `/apps/admin/src/components/table.tsx` (2 usages outside useTable)
- `/apps/merchant/src/components/table.tsx` (1 usage outside useTable)

## Problems with Current Implementation

### 1. Mixes Form and Table Concerns

The current custom `useTable` hook combines:
- Filter form management (`formItems`, `Form` component)
- Table data fetching (`useList`)
- Pagination state (`pagination`, `setPagination`)

This violates separation of concerns and makes the code harder to maintain.

### 2. Duplicates Refine's Official Functionality

Refine's official `useTable` from `@refinedev/antd` already provides:
- `tableProps` - ready-to-use props for Ant Design Table
- `searchFormProps` - ready-to-use props for search form
- `onSearch` callback - for filter transformation
- Automatic pagination handling
- Automatic filter synchronization with URL

### 3. Custom Form Component Inside Hook

The hook returns a `Form` component, which is an anti-pattern because:
- Components should be defined outside hooks for proper React reconciliation
- Makes testing difficult
- Creates tight coupling

## Refine's Official Pattern

```tsx
import { HttpError } from "@refinedev/core";
import { List, useTable, SaveButton } from "@refinedev/antd";
import { Table, Form, Input, Button, Row, Col } from "antd";

interface ISearch {
  title: string;
  status: string;
}

const MyList = () => {
  const { searchFormProps, tableProps } = useTable<IPost, HttpError, ISearch>({
    onSearch: (values) => {
      return [
        {
          field: "title",
          operator: "contains",
          value: values.title,
        },
        {
          field: "status",
          operator: "eq",
          value: values.status,
        },
      ];
    },
  });

  return (
    <List>
      {/* Search Form - separate from useTable */}
      <Form {...searchFormProps} layout="vertical">
        <Row gutter={16}>
          <Col span={6}>
            <Form.Item name="title" label="Title">
              <Input />
            </Form.Item>
          </Col>
          <Col span={6}>
            <Form.Item name="status" label="Status">
              <Select options={[...]} />
            </Form.Item>
          </Col>
          <Col span={6}>
            <Button type="primary" onClick={searchFormProps.form?.submit}>
              Search
            </Button>
            <Button onClick={() => searchFormProps.form?.resetFields()}>
              Clear
            </Button>
          </Col>
        </Row>
      </Form>

      {/* Table */}
      <Table {...tableProps} rowKey="id">
        <Table.Column title="Title" dataIndex="title" />
        <Table.Column title="Status" dataIndex="status" />
      </Table>
    </List>
  );
};
```

## Migration Strategy

### Phase 1: Create New Pattern Examples (Low Risk)

1. Create a reference implementation in a new page
2. Document the pattern for team reference
3. No changes to existing code

### Phase 2: Gradual Migration (Medium Risk)

For each page using custom `useTable`:

1. Replace import from `hooks/useTable` with `@refinedev/antd`
2. Move form JSX inline (instead of using returned `Form` component)
3. Use `onSearch` callback for filter transformation
4. Use `tableProps` directly on Table component

### Phase 3: Remove Custom Hooks (After All Pages Migrated)

1. Delete `apps/admin/src/hooks/useTable.tsx`
2. Delete `apps/merchant/src/hooks/useTable.tsx`

## Features Requiring Custom Code

Some features in the current implementation may need custom handling:

### 1. Collapsible Form Items

The current hook supports `collapse: boolean` on form items to create expandable/collapsible sections. This is not built into Refine and would need a separate component:

```tsx
// Could be extracted to a reusable component
const CollapsibleFormSection = ({ items, collapsed, onToggle }) => {
  // ... implementation
};
```

### 2. Responsive Table Wrapper

The overflow wrapper for mobile responsiveness should be kept as a simple wrapper component:

```tsx
// apps/admin/src/components/ResponsiveTableWrapper.tsx
const ResponsiveTableWrapper = ({ children }) => {
  const breakpoint = Grid.useBreakpoint();
  return (
    <div style={{
      overflowX: 'auto',
      maxWidth: breakpoint.xs || breakpoint.sm || breakpoint.md
        ? 'calc(100vw - 24px)'
        : 'auto',
    }}>
      {children}
    </div>
  );
};
```

### 3. Custom Meta Data

The current hook returns `meta` from the API response. Refine's `useTable` also supports this via:

```tsx
const { tableProps, ...tableQueryResult } = useTable({...});
const meta = tableQueryResult.tableQuery.data?.meta;
```

### 4. dayjs Formatting on Submit

The current hook auto-formats dayjs values. This can be handled in `onSearch`:

```tsx
onSearch: (values) => {
  const filters = Object.entries(values).map(([field, value]) => {
    if (dayjs.isDayjs(value)) {
      return { field, operator: "eq", value: value.format() };
    }
    return { field, operator: "eq", value };
  });
  return filters;
}
```

## Pages to Migrate

### Admin App (31 pages)

| Page | Complexity | Notes |
|------|------------|-------|
| `/pages/tag/list.tsx` | Low | Simple, no form |
| `/pages/merchant/list.tsx` | High | Complex with meta, batch actions |
| `/pages/thirdChannel/list.tsx` | Medium | Standard form |
| `/pages/thirdChannel/setting/list.tsx` | Medium | Standard form |
| `/pages/userChannel/list.tsx` | High | Complex with many filters |
| `/pages/transaction/collection/list.tsx` | High | Complex form with collapse |
| `/pages/transaction/deposit/list.tsx` | High | Complex form with collapse |
| `/pages/transaction/fund/list.tsx` | Medium | Standard form |
| `/pages/transaction/message/list.tsx` | Low | Simple form |
| `/pages/transaction/PayForAnother/list.tsx` | Medium | Standard form |
| `/pages/live/list.tsx` | Medium | Uses custom Table component |
| `/pages/providers/list.tsx` | Medium | Standard form |
| `/pages/providers/wallet-history/list.tsx` | Medium | Standard form |
| `/pages/providers/user-wallet-history/list.tsx` | Medium | Standard form |
| `/pages/providers/whiteList.tsx` | Low | Simple form |
| `/pages/provider/list.tsx` | Medium | Standard form |
| `/pages/provider/deposit/list.tsx` | Medium | Standard form |
| `/pages/provider/transaction/list.tsx` | Medium | Standard form |
| `/pages/merchant/wallet-history/list.tsx` | Medium | Standard form |
| `/pages/merchant/user-wallet-history/list.tsx` | Medium | Standard form |
| `/pages/merchant/whiteList.tsx` | Low | Simple form |
| `/pages/merchant/bannedList.tsx` | Low | Simple form |
| `/pages/merchant/apiWhiteList.tsx` | Low | Simple form |
| `/pages/systemSetting/list.tsx` | Low | Simple |
| `/pages/systemSetting/bank/list.tsx` | Low | Simple |
| `/pages/userBankCard/list.tsx` | Medium | Standard form |
| `/pages/loginWhiteList/list.tsx` | Low | Simple form |
| `/pages/permissions/list.tsx` | Low | Simple |
| `/pages/channel/list.tsx` | Medium | Standard form |
| `/pages/financeStatitic/list.tsx` | Medium | Standard form |

### Merchant App (6 pages)

| Page | Complexity | Notes |
|------|------------|-------|
| `/pages/collection/list.tsx` | Medium | Standard form |
| `/pages/PayForAnother/list.tsx` | Medium | Standard form |
| `/pages/member/list.tsx` | Medium | Standard form |
| `/pages/subAccount/list.tsx` | Medium | Uses custom Table component |
| `/pages/wallet-history/index.tsx` | Medium | Standard form |
| `/pages/bankCard/list.tsx` | Medium | Standard form |

## Recommended Migration Order

1. Start with **low complexity** pages to establish the pattern
2. Move to **medium complexity** pages
3. Tackle **high complexity** pages last

## Verification Checklist

For each migrated page:

- [ ] Table loads data correctly
- [ ] Pagination works
- [ ] Filters work correctly
- [ ] Reset/clear filters works
- [ ] Page size selection works (if applicable)
- [ ] URL synchronization works (if using syncWithLocation)
- [ ] Loading states display correctly
- [ ] Error states display correctly

## Removed Files

The following files were removed as part of this task:

- `/apps/admin/src/hooks/useCreateForm.tsx` - Unused hook, never imported anywhere

## Next Steps

1. Pick a low-complexity page to migrate first (e.g., `/pages/tag/list.tsx`)
2. Test thoroughly
3. Document any issues encountered
4. Continue with remaining pages
