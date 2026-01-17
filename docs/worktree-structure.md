# Git Worktree 結構

## Worktree 列表

1. **upgrade/prepare** - `.worktrees/upgrade-prepare`
   - 準備階段：環境設置、腳本準備、基準測試

2. **upgrade/laravel-8** - `.worktrees/upgrade-laravel-8`
   - Laravel 7 → 8 升級

3. **upgrade/laravel-9** - `.worktrees/upgrade-laravel-9`
   - Laravel 8 → 9 升級

4. **upgrade/laravel-10** - `.worktrees/upgrade-laravel-10`
   - Laravel 9 → 10 升級

5. **upgrade/laravel-11** - `.worktrees/upgrade-laravel-11`
   - Laravel 10 → 11 升級

6. **upgrade/php-8.3** - `.worktrees/upgrade-php-8.3`
   - PHP 8.0 → 8.3 升級

7. **upgrade/cleanup** - `.worktrees/upgrade-cleanup`
   - 依賴清理與代碼現代化

## 工作流程

每個階段：
1. 在對應 worktree 工作
2. 完成後 commit
3. 下一階段 merge 前一階段的變更
4. 驗證功能正常
5. 繼續下一階段

## 清理指令

```bash
# 移除所有 worktrees（完成後）
git worktree remove .worktrees/upgrade-prepare
git worktree remove .worktrees/upgrade-laravel-8
git worktree remove .worktrees/upgrade-laravel-9
git worktree remove .worktrees/upgrade-laravel-10
git worktree remove .worktrees/upgrade-laravel-11
git worktree remove .worktrees/upgrade-php-8.3
git worktree remove .worktrees/upgrade-cleanup
```
