# Bug: Client Area VM Actions Not Working

**Date Reported:** 2026-01-09
**Date Fixed:** 2026-01-09
**Status:** Fixed
**Severity:** High
**Affected Components:** Client area VM actions (Stop, Restart, VNC Console, Reset Password)

## Problem

The client area VM actions (Stop VM, Restart VM, VNC Console, Reset Password) shown in the "Actions" panel are not working. Users click the buttons but nothing happens and no error messages are displayed.

![Screenshot showing Actions panel](./client-area-actions-not-working.png)

## Root Causes

### Issue 1: Method Name Mismatch (VNC Console - Fatal Error)

**Location:** `modules/servers/cloudpe/cloudpe.php` lines 862, 1106

The code calls a method that doesn't exist in the API class:

```php
// Code calls:
$result = $api->getVncConsole($serverId);  // Method doesn't exist!

// But CloudPeAPI.php defines:
public function getConsoleUrl(string $serverId, string $type = 'novnc'): array
```

**Impact:** Clicking "VNC Console" triggers a fatal PHP error: `Call to undefined method CloudPeAPI::getVncConsole()`

### Issue 2: Return Value Format Mismatch (VNC Console)

**Location:** `modules/servers/cloudpe/cloudpe.php` line 1110

Even if the method name is fixed, the return value handling is incorrect:

```php
// cloudpe_ClientConsole() expects:
if ($result['success'] && !empty($result['console']['url'])) {
    header('Location: ' . $result['console']['url']);
}

// But getConsoleUrl() returns:
return ['success' => true, 'url' => $url];  // No 'console' key!
```

**Impact:** Even with correct method name, the redirect URL would never be extracted.

### Issue 3: WHMCS Silent Error Handling

**Location:** All client area functions in `modules/servers/cloudpe/cloudpe.php`

WHMCS does not display error strings returned by client area custom functions. When functions return errors like `'Failed: ...'` or `'Error: ...'`, WHMCS simply redirects back to the product details page without showing any message.

```php
function cloudpe_ClientStop(array $params): string {
    // ...
    if (!$result['success']) {
        return 'Failed: ' . ($result['error'] ?? 'Unknown error');  // User never sees this!
    }
    // ...
}
```

**Impact:** All API failures, authentication errors, or validation errors are silently swallowed.

## Affected Functions

| Function                         | File Location    | Issue                                      |
| -------------------------------- | ---------------- | ------------------------------------------ |
| `cloudpe_ClientStart()`          | cloudpe.php:958  | Silent error handling                      |
| `cloudpe_ClientStop()`           | cloudpe.php:1008 | Silent error handling                      |
| `cloudpe_ClientRestart()`        | cloudpe.php:1046 | Silent error handling                      |
| `cloudpe_ClientConsole()`        | cloudpe.php:1096 | Method name + return value + silent errors |
| `cloudpe_ClientChangePassword()` | cloudpe.php:1122 | Silent error handling                      |

## Solution

### Fix 1: Correct Method Name

Change `getVncConsole` to `getConsoleUrl` at:

- Line 862 (admin area console)
- Line 1106 (client area console)

### Fix 2: Correct Return Value Handling

Update `cloudpe_ClientConsole()` to use `$result['url']` instead of `$result['console']['url']`:

```php
if ($result['success'] && !empty($result['url'])) {
    header('Location: ' . $result['url']);
    exit;
}
```

### Fix 3: Add User-Visible Error Messages

Implement session-based messaging to display errors/success to users:

1. Set messages in session before returning:

```php
$_SESSION['cloudpe_message'] = 'VM stopped successfully';
$_SESSION['cloudpe_message_type'] = 'success';
```

2. Pass messages to template in `cloudpe_ClientArea()`:

```php
$message = $_SESSION['cloudpe_message'] ?? null;
$messageType = $_SESSION['cloudpe_message_type'] ?? 'info';
unset($_SESSION['cloudpe_message'], $_SESSION['cloudpe_message_type']);
```

3. Display in `templates/overview.tpl`:

```smarty
{if $cloudpe_message}
    <div class="alert alert-{$cloudpe_message_type|default:'info'} alert-dismissible">
        <button type="button" class="close" data-dismiss="alert">&times;</button>
        {$cloudpe_message}
    </div>
{/if}
```

## Files to Modify

1. `modules/servers/cloudpe/cloudpe.php`

   - Fix method name (2 locations)
   - Fix return value handling
   - Add session-based messaging to all 5 client functions
   - Update `cloudpe_ClientArea()` to pass messages to template

2. `modules/servers/cloudpe/templates/overview.tpl`
   - Add alert display block

## Testing Checklist

- [ ] VNC Console opens correctly
- [ ] Stop VM shows success message
- [ ] Stop VM shows error message on failure
- [ ] Start VM shows success message
- [ ] Start VM shows error message on failure
- [ ] Restart VM shows success message
- [ ] Restart VM shows error message on failure
- [ ] Reset Password shows success message
- [ ] Reset Password shows error message on failure
