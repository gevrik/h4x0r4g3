(function () {

    // TODO do not display strings in here or any js file, only display strings that have been sent by the server

    $(document).on('ready', function () {

        var md = $('#messages');
        var commandInput = $('#command-input');

        var ticker;

        // site ready
        console.log('site ready');

        // input history
        commandInput.inputHistory({
            size: 5
        });

        $('#panel-container').on('click', '.panel-heading .close', function(e){
            $('#panel-container').html('');
            commandInput.focus();
        });

        $('#panel-container').on('click', '.close-btn', function(e){
            var command = {
                command: 'parseInput',
                hash: hash,
                content: 'dismissnotification',
                silent: true,
                entityId: $(this).data('notification-id')
            };
            conn.send(JSON.stringify(command));
            commandInput.focus();
        });

        $('#panel-container').on('click', '#btn-dismiss-all-notifications', function(e){
            var command = {
                command: 'parseInput',
                hash: hash,
                content: 'dismissallnotifications',
                silent: true
            };
            conn.send(JSON.stringify(command));
            $('#panel-container').html('');
            commandInput.focus();
        });

        $('.notification-box').on('click', function(e){
            var command = {
                command: 'parseInput',
                hash: hash,
                content: 'shownotifications',
                silent: true
            };
            conn.send(JSON.stringify(command));
        });

        // resize message div to be full height
        var vph = $(window).height() - 5;
        md.css({'height': vph + 'px', 'max-height': vph + 'px'});

        // websocket stuff
        conn = new WebSocket(wsprotocol + '://' + wshost + ':' + wsport);
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
            var messageArray;
            var textClass = 'muted';
            var data = JSON.parse(e.data);
            var command = data.command;
            if (command !== 'showmessageprepend' && command !== 'updateprompt' && command !== 'ticker') commandInput.attr('type', 'text').detach();
            switch (command) {
                default:
                    console.log('=== unknown command received ===');
                    break;
                case 'cd':
                    jsonData = {
                        command: 'parseInput',
                        hash: hash,
                        content: 'ls',
                        silent: true
                    };
                    conn.send(JSON.stringify(jsonData));
                    break;
                case 'echocommand':
                    if (loginStage !== 'createpassword' && loginStage !== 'createpasswordconfirm' && loginStage !== 'promptforpassword') {
                        md.append(data.content + '<br />');
                    }
                    else if (command === 'updateprompt') {

                    }
                    else {
                        md.append('<br />');
                    }
                    break;
                case 'confirmusercreate':
                    loginStage = 'confirmusercreate';
                    md.append('<span class="text-muted">Unknown user, do you want to create an account for this username? (y/n) </span>');
                    break;
                case 'createpassword':
                    loginStage = 'createpassword';
                    //commandInput.attr('type', 'password');
                    md.append('<span class="text-muted">Please enter a password for the new user: </span>');
                    break;
                case 'createpasswordconfirm':
                    loginStage = 'createpasswordconfirm';
                    //commandInput.attr('type', 'password');
                    md.append('<span class="text-muted">Please confirm the password for the new user: </span>');
                    break;
                case 'createuserdone':
                    loginStage = 'complete';
                    hash = data.hash;
                    md.append('<span class="text-muted">A new user account and system has been generated.</span><br />');
                    md.append('<span class="text-info">Welcome to NeoCortex OS v0.1</span><br />');
                    showprompt();
                    break;
                case 'promptforpassword':
                    loginStage = 'promptforpassword';
                    //commandInput.attr('type', 'password');
                    md.append('<span class="text-muted">password: </span>');
                    break;
                case 'logincomplete':
                    var jsonData;
                    loginStage = 'complete';
                    resetConsoleOptionsMail();
                    hash = data.hash;
                    md.append('<span class="text-muted">Authentication complete.</span><br />');
                    md.append('<span class="text-info">Welcome to NeoCortex OS v0.1 (ANONYMOUS ADWARE)</span><br />');
                    jsonData = {
                        command: 'parseInput',
                        hash: hash,
                        content: 'showunreadmails',
                        silent: true
                    };
                    conn.send(JSON.stringify(jsonData));
                    ticker = window.setInterval(function(){
                        jsonData = {
                            command: 'parseInput',
                            hash: hash,
                            content: 'ticker',
                            silent: true
                        };
                        conn.send(JSON.stringify(jsonData));
                    }, 1000);
                    break;
                case 'showprompt':
                    var message = data.message;
                    if (promptAddon != '') message = message + ' ' + promptAddon;
                    md.append('<span class="text-muted">' + message + '</span>');
                    break;
                case 'ticker':
                    var notiAmount = data.amount;
                    $('.notification-box').removeClass('btn-default').removeClass('btn-info').addClass((notiAmount>0)?'btn-info':'btn-default').html('<span>' + notiAmount + '</span>');
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
                            case 3:
                                textClass = 'executable';
                                break;
                            case 4:
                                textClass = 'textfile';
                                break;
                        }
                        if (file.running) {
                            file.name = '*' + file.name;
                        }
                        md.append('<span class="text-' + textClass + '">' + file.name + '</span><br />');
                    });
                    showprompt();
                    break;
                case 'clear':
                    md.html('');
                    showprompt();
                    break;
                case 'entercodemode':
                    consoleMode = 'code';
                    md.append(data.message);
                    break;
                case 'entermailmode':
                    consoleMode = 'mail';
                    consoleOptionsMail.currentMailNumber = (data.mailNumber < 1) ? 0 : 1;
                    md.append(data.message);
                    break;
                case 'exitcodemode':
                    consoleMode = 'default';
                    showprompt();
                    break;
                case 'exitmailmode':
                    consoleMode = 'default';
                    resetConsoleOptionsMail();
                    showprompt();
                    break;
                case 'showpanel':
                    $('#panel-container').html('').append(data.content);
                    $('.draggable').draggable({
                        handle: '.panel-heading'
                    });
                    if (!data.silent) showprompt();
                    break;
                case 'showmessage':
                    md.append(data.message);
                    showprompt();
                    break;
                case 'showoutput':
                    messageArray = data.message;
                    $.each(messageArray, function(i, messageData){
                        md.append(messageData);
                    });
                    showprompt();
                    break;
                case 'updateprompt':
                    commandInput.val(data.message);
                    break;
                case 'showmessageprepend':
                    var lastPrompt = $('.prompt').last();
                    $(data.message).insertBefore(lastPrompt);
                    return true;
            }
            if (command !== 'echocommand' && command !== 'updateprompt' && command !== 'ticker') {
                commandInput.appendTo(md).focus();
            }
        };
        conn.onclose = function() {
            md.append('<br /><span class="text-danger">Connection to NeoCortex network lost</span>');
            $('#command-input').remove();
            clearInterval(ticker);
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
                            case 'confirmusercreate':
                                jsonData = {
                                    command: 'confirmusercreate',
                                    hash: hash,
                                    content: message
                                };
                                break;
                            case 'createpassword':
                                jsonData = {
                                    command: 'createpassword',
                                    hash: hash,
                                    content: message
                                };
                                break;
                            case 'createpasswordconfirm':
                                jsonData = {
                                    command: 'createpasswordconfirm',
                                    hash: hash,
                                    content: message
                                };
                                break;
                            case 'promptforpassword':
                                jsonData = {
                                    command: 'promptforpassword',
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
                            content: message
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
