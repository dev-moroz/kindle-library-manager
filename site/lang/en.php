<?php

/**
 * English locale.
 * Returns a "key => text" array.
 * Placeholders like {name} are replaced by t($key, ['name' => 'value']).
 */

return [

    // --- Access / moderation ---
    'new_user_request'       => "🔔 New access request\n\nName: {first_name}\nUsername: @{username}\nID: {user_id}",
    'access_denied_pending'  => "🔒 Your access request has been sent to the administrator. Please wait for confirmation.",
    'fallback_no_username'   => "No_username",
    'fallback_no_name'       => "No_name",
    'access_approved_admin'  => "✅ User {user_id} has been approved.",
    'access_approved_user'   => "🎉 Access granted! You can now send books to the bot.",
    'access_rejected_admin'  => "❌ User {user_id}'s request has been rejected.",
    'access_rejected_user'   => "😔 Unfortunately, your access request was denied.",

    // --- Buttons ---
    'btn_approve'      => "✅ Approve",
    'btn_reject'       => "❌ Reject",
    'btn_yes'          => "Yes",
    'btn_no'           => "No",
    'btn_open_library' => "📚 Open library",

    // --- File renaming ---
    'rename_question' => "📖 File «{file_name}» received.\nRename it before saving?",
    'enter_new_name'  => "✏️ Enter a new file name:",
    'action_outdated' => "⚠️ This action is outdated.",

    // --- Upload and conversion ---
    'converting'      => "🔄 The .{ext} format is not supported directly. Converting to .{format}…",
    'convert_success' => "✅ File converted and saved: {file_name}",
    'convert_error'   => "⚠️ Failed to convert the file. Saved it as is: {file_name}",
    'upload_success'  => "✅ File saved: {file_name}",

    // --- Misc ---
    'library_available' => "📚 The library is available via the button below 👇",
    'log_write_error'   => "Error writing to file {file}",

];
