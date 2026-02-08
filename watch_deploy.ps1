# 
# 自動デプロイスクリプト (watch_deploy.ps1)
# 
$watcher = New-Object System.IO.FileSystemWatcher
$watcher.Path = (Get-Location).Path
$watcher.Filter = "*.*"
$watcher.IncludeSubdirectories = $true
$watcher.EnableRaisingEvents = $true

# 同時に複数のイベントが発生した際の重複実行を防ぐためのフラグ
$global:isDeploying = $false

$action = {
    if ($global:isDeploying) { return }
    $global:isDeploying = $true

    $path = $Event.SourceEventArgs.FullPath
    $changeType = $Event.SourceEventArgs.ChangeType
    
    # 特定のディレクトリ（.git やブレイン内）は無視する
    if ($path -like "*\.git\*" -or $path -like "*\brain\*") {
        $global:isDeploying = $false
        return
    }

    Write-Host "[$(Get-Date -Format 'HH:mm:ss')] 変更検知: $path ($changeType)" -ForegroundColor Cyan
    
    # 保存完了を待つ、および連続保存をまとめるための待機
    Start-Sleep -Seconds 2
    
    Write-Host "自動デプロイ（Git Push）を開始します..." -ForegroundColor Yellow
    try {
        git add .
        git commit -m "Auto-deploy: $(Get-Date -Format 'yyyy-MM-dd HH:mm:ss')"
        git push
        Write-Host "デプロイ完了。" -ForegroundColor Green
    } catch {
        Write-Host "エラーが発生しました: $_" -ForegroundColor Red
    } finally {
        $global:isDeploying = $false
    }
}

# イベントの登録
$handlers = @()
$handlers += Register-ObjectEvent $watcher "Changed" -Action $action
$handlers += Register-ObjectEvent $watcher "Created" -Action $action
$handlers += Register-ObjectEvent $watcher "Deleted" -Action $action
$handlers += Register-ObjectEvent $watcher "Renamed" -Action $action

Write-Host "--------------------------------------------------" -ForegroundColor Magenta
Write-Host "  自動デプロイ監視中..." -ForegroundColor Magenta
Write-Host "  このウィンドウを開いたままにしてください。" -ForegroundColor Magenta
Write-Host "  終了するには Ctrl + C を押してください。" -ForegroundColor Magenta
Write-Host "--------------------------------------------------" -ForegroundColor Magenta

try {
    while ($true) { Start-Sleep -Seconds 1 }
} finally {
    # 終了時にイベント登録を解除
    foreach ($h in $handlers) { Unregister-Event -SourceIdentifier $h.Name }
    $watcher.Dispose()
    Write-Host "監視を終了しました。"
}
