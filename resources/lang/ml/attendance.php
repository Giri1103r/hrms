<?php

return [
    // Shared
    'employee' => 'ജീവനക്കാരൻ',
    'select_employee' => 'ജീവനക്കാരനെ തിരഞ്ഞെടുക്കുക',
    'employee_id' => 'ജീവനക്കാരന്റെ ഐഡി',
    'employee_name' => 'ജീവനക്കാരന്റെ പേര്',
    'reporting_to' => 'റിപ്പോർട്ട് ചെയ്യുന്നത്',
    'employee_details' => 'ജീവനക്കാരന്റെ വിവരങ്ങൾ',
    'request_details' => 'അഭ്യർത്ഥന വിവരങ്ങൾ',
    'request_log' => 'അഭ്യർത്ഥന രേഖ',
    'request_no' => 'അഭ്യർത്ഥന നമ്പർ',
    'request_status' => 'അഭ്യർത്ഥന നില',
    'select_status' => 'നില തിരഞ്ഞെടുക്കുക',
    'all_requests' => 'എല്ലാ അഭ്യർത്ഥനകളും',
    'waiting_my_approval' => 'എന്റെ അംഗീകാരത്തിനായി കാത്തിരിക്കുന്നു',
    'approver' => 'അംഗീകരിക്കുന്നയാൾ',
    'raised_by' => 'സമർപ്പിച്ചത്',
    'marked_by' => 'അടയാളപ്പെടുത്തിയത്',
    'marked_on' => 'അടയാളപ്പെടുത്തിയ തീയതി',
    'by' => 'ആരാൽ',
    'reason' => 'കാരണം',
    'approval' => 'അംഗീകാരം',
    'decided_by' => ':status ആരാൽ',
    'remarks_reject_hint' => '(നിരസിക്കുമ്പോൾ ആവശ്യമാണ്)',
    'approve_note' => 'അഭ്യർത്ഥന സമർപ്പിച്ചയാളെ ഏത് തീരുമാനവും അറിയിക്കും.',
    'direct_note' => 'ഒരു മാനേജർ എന്ന നിലയിൽ, ഈ :item നേരിട്ട് അംഗീകരിക്കപ്പെടുന്നു.',
    'routed_note' => 'ഈ അഭ്യർത്ഥന നിങ്ങളുടെ റിപ്പോർട്ടിംഗ് മാനേജർ :name ന് അംഗീകാരത്തിനായി അയയ്ക്കും.',
    'not_set' => '(സജ്ജമാക്കിയിട്ടില്ല)',
    'myself' => 'ഞാൻ തന്നെ',
    'designation' => 'പദവി',
    'no_reports' => 'ഇതുവരെ ആരും നിങ്ങൾക്ക് റിപ്പോർട്ട് ചെയ്യുന്നില്ല.',

    // Permission
    'permission_date' => 'അനുമതി തീയതി',
    'permission_details' => 'അനുമതി വിവരങ്ങൾ',
    'time' => 'സമയം',
    'time_from' => 'തുടങ്ങുന്ന സമയം',
    'time_to' => 'അവസാനിക്കുന്ന സമയം',
    'duration' => 'ദൈർഘ്യം',
    'duration_hrs' => 'ദൈർഘ്യം (മണിക്കൂർ)',

    // Compensatory off
    'worked_date' => 'ജോലി ചെയ്ത തീയതി',
    'worked_date_hint' => 'ജീവനക്കാരൻ ജോലി ചെയ്ത അവധി / ആഴ്ചയിലെ ഒഴിവ് ദിവസം.',
    'compoff_date' => 'പകരം അവധി തീയതി',
    'compoff_date_hint' => 'പകരമായി ക്ലെയിം ചെയ്യുന്ന അവധി ദിവസം.',
    'compoff_details' => 'പകരം അവധി വിവരങ്ങൾ',

    // Daily attendance
    'attendance_date' => 'ഹാജർ തീയതി',
    'attendance_type' => 'ഹാജർ തരം',
    'attendance_details' => 'ഹാജർ വിവരങ്ങൾ',
    'select_employees' => 'ജീവനക്കാരെ തിരഞ്ഞെടുക്കുക',
    'go_to_attendance_approval' => 'ഹാജർ അംഗീകാരത്തിലേക്ക് പോകുക',

    // Attendance approval
    'pending_attendance_approvals' => 'തീർപ്പാക്കാത്ത ഹാജർ അംഗീകാരങ്ങൾ',
    'no_pending_approval' => 'നിങ്ങളുടെ അംഗീകാരത്തിനായി ഒരു ഹാജറും കാത്തിരിക്കുന്നില്ല.',

    // Missed attendance
    'missed_attendance' => 'നഷ്ടപ്പെട്ട ഹാജർ',
    'missed_attendance_note' => 'കഴിഞ്ഞ :days ദിവസങ്ങളിൽ ഹാജർ രേഖപ്പെടുത്താത്ത പ്രവൃത്തി ദിനങ്ങൾ (വാരാന്ത്യങ്ങൾ, അവധി ദിനങ്ങൾ, നേരത്തെ രേഖപ്പെടുത്തിയ അല്ലെങ്കിൽ അവധിയിലുള്ള ദിവസങ്ങൾ ഒഴികെ). രേഖപ്പെടുത്തേണ്ട ദിവസങ്ങൾ തിരഞ്ഞെടുത്ത് സമർപ്പിക്കുക.',
    'no_missed_attendance' => 'രേഖപ്പെടുത്താൻ നഷ്ടപ്പെട്ട ഹാജർ ഒന്നുമില്ല. എല്ലാം കൃത്യമാണ്.',
    'day' => 'ദിവസം',
    'no_log_entries' => 'രേഖകളൊന്നുമില്ല',
    'on_date' => ':date ന്',
    'action_remarks' => 'നടപടി അഭിപ്രായങ്ങൾ',
    'select_one_entry_alert' => 'ദയവായി ഒരു ഹാജർ എൻട്രിയെങ്കിലും തിരഞ്ഞെടുക്കുക',
    'confirm_approve' => 'തിരഞ്ഞെടുത്ത എൻട്രികൾ അംഗീകരിക്കണോ?',
    'confirm_reject' => 'തിരഞ്ഞെടുത്ത എൻട്രികൾ നിരസിക്കണോ?',
    'confirm_approve_request' => 'ഈ അഭ്യർത്ഥന അംഗീകരിക്കണോ?',
    'confirm_reject_request' => 'ഈ അഭ്യർത്ഥന നിരസിക്കണോ?',
    'select_one_date_alert' => 'ദയവായി ഒരു തീയതിയെങ്കിലും തിരഞ്ഞെടുക്കുക',
];
