(function () {

    // TODO do not display strings in here or any js file, only display strings that have been sent by the server

    $(document).on('ready', function () {

        var md = $('#messages');
        var commandInput = $('#command-input');

        // site ready
        console.log('site ready');

        // input history
        commandInput.inputHistory({
            size: 5
        });

        // resize message div to be full height
        var vph = $(window).height() - 5;
        md.css({'height': vph + 'px', 'max-height': vph + 'px'});

        // websocket stuff
        conn = new WebSocket('wss://dev.h4x0r4g3.com:8082');
        // even listener for connection open
        conn.onopen = function() {
            commandInput.detach();
            // fluff
            md.append('<span class="text-info">Establishing connection to NeoCortex network...</span><br />');
            // check username
            md.append('<span class="text-muted">login: </span>');
            commandInput.appendTo(md).focus();
        };
        // event listener for server message
        conn.onmessage = function(e) {
            //console.log(e.data);
            var messageArray;
            var textClass = 'muted';
            var data = JSON.parse(e.data);
            var command = data.command;
            if (command != 'showMessagePrepend' && command != 'updatePrompt') commandInput.attr('type', 'text').detach();
            switch (command) {
                default:
                    console.log('=== unknown command received ===');
                    break;
                case 'echoCommand':
                    if (loginStage != 'createPassword' && loginStage != 'createPasswordConfirm' && loginStage != 'promptForPassword') {
                        md.append(data.content + '<br />');
                    }
                    else if (command == 'updatePrompt') {

                    }
                    else {
                        md.append('<br />');
                    }
                    break;
                case 'confirmUserCreate':
                    loginStage = 'confirmUserCreate';
                    md.append('<span class="text-muted">Unknown user, do you want to create an account for this username? (y/n) </span>');
                    break;
                case 'createPassword':
                    loginStage = 'createPassword';
                    //commandInput.attr('type', 'password');
                    md.append('<span class="text-muted">Please enter a password for the new user: </span>');
                    break;
                case 'createPasswordConfirm':
                    loginStage = 'createPasswordConfirm';
                    //commandInput.attr('type', 'password');
                    md.append('<span class="text-muted">Please confirm the password for the new user: </span>');
                    break;
                case 'createUserDone':
                    loginStage = 'complete';
                    hash = data.hash;
                    md.append('<span class="text-muted">A new user account and system has been generated.</span><br />');
                    md.append('<span class="text-info">Welcome to NeoCortex OS v0.1</span><br />');
                    showPrompt();
                    break;
                case 'promptForPassword':
                    loginStage = 'promptForPassword';
                    //commandInput.attr('type', 'password');
                    md.append('<span class="text-muted">password: </span>');
                    break;
                case 'loginComplete':
                    loginStage = 'complete';
                    resetConsoleOptionsMail();
                    resetConsoleOptionsCode();
                    hash = data.hash;
                    md.append('<span class="text-muted">Authentication complete.</span><br />');
                    md.append('<span class="text-info">Welcome to NeoCortex OS v0.1 (ANONYMOUS ADWARE)</span><br />');
                    var jsonData = {
                        command: 'parseInput',
                        hash: hash,
                        content: 'showunreadmails',
                        silent: true
                    };
                    conn.send(JSON.stringify(jsonData));
                    break;
                case 'showPrompt':
                    var message = data.message;
                    if (promptAddon != '') message = message + ' ' + promptAddon;
                    md.append('<span class="text-muted">' + message + '</span>');
                    break;
                case 'refreshPrompt':
                    showPrompt();
                    break;
                case 'ls':
                    var directoryArray = data.message;
                    $.each(directoryArray, function(i, file){
                        switch (file.type) {
                            default:
                                textClass = 'muted';
                                break;
                            case 1:
                                textClass = 'directory';
                                file.name = '/' + file.name;
                                break;
                            case 2:
                                textClass = 'executable';
                                break;
                        }
                        if (file.running) {
                            file.name = '*' + file.name;
                        }
                        md.append('<span class="text-' + textClass + '">' + file.name + '</span><br />');
                    });
                    showPrompt();
                    break;
                case 'clear':
                    md.html('');
                    showPrompt();
                    break;
                case 'enterCodeMode':
                    consoleMode = 'code';
                    md.append('<span class="text-' + data.type + '">' + data.message + '</span><br />');
                    break;
                case 'enterMailMode':
                    consoleMode = 'mail';
                    consoleOptionsMail.currentMailNumber = (data.mailNumber < 1) ? 0 : 1;
                    md.append('<span class="text-' + data.type + '">' + data.message + '</span><br />');
                    break;
                case 'exitCodeMode':
                    consoleMode = 'default';
                    resetConsoleOptionsCode();
                    showPrompt();
                    break;
                case 'exitMailMode':
                    consoleMode = 'default';
                    resetConsoleOptionsMail();
                    showPrompt();
                    break;
                case 'ps':
                    var fileArray = data.message;
                    $.each(fileArray, function(i, fileData){
                        md.append(fileData);
                    });
                    showPrompt();
                    break;
                case 'score':
                    messageArray = data.message;
                    $.each(messageArray, function(i, messageData){
                        md.append(messageData);
                    });
                    showPrompt();
                    break;
                case 'setCodeLevel':
                    consoleOptionsCode.fileLevel = data.value;
                    md.append('<span class="text-' + data.type + '">' + data.message + '</span><br />');
                    showPrompt();
                    console.log(data);
                    break;
                case 'setCodeType':
                    consoleOptionsCode.fileType = data.value;
                    md.append('<span class="text-' + data.type + '">' + data.message + '</span><br />');
                    showPrompt();
                    console.log(data);
                    break;
                case 'skills':
                    messageArray = data.message;
                    $.each(messageArray, function(i, messageData){
                        md.append(messageData);
                    });
                    showPrompt();
                    break;
                case 'showMessage':
                    md.append('<span class="text-' + data.type + '">' + data.message + '</span><br />');
                    showPrompt();
                    break;
                case 'stat':
                    messageArray = data.message;
                    $.each(messageArray, function(i, messageData){
                        md.append(messageData);
                    });
                    showPrompt();
                    break;
                case 'system':
                    messageArray = data.message;
                    $.each(messageArray, function(i, messageData){
                        md.append(messageData);
                    });
                    showPrompt();
                    break;
                case 'updatePrompt':
                    commandInput.val(data.message);
                    break;
                case 'showMessagePrepend':
                    var lastPrompt = $('.prompt').last();
                    $('<span class="text-' + data.type + '">' + data.message + '</span><br />').insertBefore(lastPrompt);
                    return true;
            }
            if (command != 'echoCommand' && command != 'updatePrompt') {
                commandInput.appendTo(md).focus();
            }
        };
        conn.onclose = function() {
            md.append('<br /><span class="text-danger">Connection to NeoCortex network lost</span>');
            $('#command-input').remove();
        };
        // sending input
        $('#main-content').on('keydown', '#command-input', function(event){
            var keycode = (event.keyCode ? event.keyCode : event.which);
            var jsonData;
            var message = '';
            // enter
            if(keycode === 13) {
                // enter key pressed in input
                message = $(this).val();
                $.trim(message);
                $(this).val('');
                switch (consoleMode) {
                    default:
                        switch (loginStage) {
                            default:
                                conn.close();
                                break;
                            case 'login':
                                jsonData = {
                                    command: 'login',
                                    hash: hash,
                                    content: message
                                };
                                break;
                            case 'confirmUserCreate':
                                jsonData = {
                                    command: 'confirmUserCreate',
                                    hash: hash,
                                    content: message
                                };
                                break;
                            case 'createPassword':
                                jsonData = {
                                    command: 'createPassword',
                                    hash: hash,
                                    content: message
                                };
                                break;
                            case 'createPasswordConfirm':
                                jsonData = {
                                    command: 'createPasswordConfirm',
                                    hash: hash,
                                    content: message
                                };
                                break;
                            case 'promptForPassword':
                                jsonData = {
                                    command: 'promptForPassword',
                                    hash: hash,
                                    content: message
                                };
                                break;
                            case 'complete':
                                jsonData = {
                                    command: 'parseInput',
                                    hash: hash,
                                    content: message
                                };
                                break;
                        }
                        break;
                    case 'mail':
                        jsonData = {
                            command: 'parseMailInput',
                            hash: hash,
                            content: message,
                            mailOptions: consoleOptionsMail
                        };
                        break;
                    case 'code':
                        jsonData = {
                            command: 'parseCodeInput',
                            hash: hash,
                            content: message,
                            codeOptions: consoleOptionsCode
                        };
                        break;
                }
                conn.send(JSON.stringify(jsonData));
            }
            // tab
            if(keycode === 9){
                event.preventDefault();
                message = $(this).val();
                jsonData = {
                    command: 'autocomplete',
                    hash: hash,
                    content: message
                };
                conn.send(JSON.stringify(jsonData));
            }
        })
            .on('click', function(){
                commandInput.focus();
            });
    });
}).call();
