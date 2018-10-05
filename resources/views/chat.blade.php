@extends('layouts.app')

@section('content')
    <div class="chat-wrapper">
        <div id="message-box"></div>
        <div id="userslist-box"></div>
        <div class="user-panel">
            <input type="text" name="message" id="message" placeholder="Type your message here..." maxlength="191"/>
            <button id="send-message">Send</button>
        </div>
    </div>
@endsection

@section('page.script')
    <script>
        //create a new WebSocket object.
        var msgBox = $('#message-box');
        var usrBox = $('#userslist-box');
        var userId = {{\Illuminate\Support\Facades\Auth::id()}};
        var wsUri = "ws://localhost:8001?uid=" + userId;
        websocket = new WebSocket(wsUri);

        //Connection is open
        websocket.onopen = function () {
            //get messages story
            $.ajax({
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                url: '/messages',
                type: 'GET',
                success: function (res) {
                    $.map(res, function (item) {
                        msgBox.append(
                                '<div class="system_msg" style="color:#bbbbbb">' + item.created_at +
                                '</div><div><span class="user_name">' + item.user.name +
                                '</span> : <span class="user_message">' + item.message + '</span></div>'
                        );
                    });
                }
            });
        };

        // Message received from server
        websocket.onmessage = function (ev) {
            var msg = JSON.parse(ev.data); //PHP sends Json data
            switch (msg.type) {
                case 'usermsg':
                    msgBox.append(
                            '<div class="system_msg" style="color:#bbbbbb">' + msg.created_at +
                            '</div><div><span class="user_name">' + msg.username +
                            '</span> : <span class="user_message">' + msg.text + '</span></div>'
                    );
                    break;
                case 'system':
                    msgBox.append('<div style="color:#bbbbbb">' + msg.text + '</div>');
                    break;

                case 'userin': case 'userout':
                        $.ajax({
                            headers: {
                                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                            },
                            url: '/chat/user/list',
                            type: 'POST',
                            data: msg,
                            dataType: 'json',
                            success: function (res) {
                                usrBox.empty();
                                res.users.forEach(function(name, i, arr) {
                                    usrBox.append('<div><h6>' + name + '</h6></div>');
                                });
                            }
                        });
                break;
            }
            msgBox[0].scrollTop = msgBox[0].scrollHeight; //scroll message
        };

        websocket.onerror = function (ev) {
            msgBox.append('<div class="system_error">Error Occurred - ' + ev.data + '</div>');
        };
        websocket.onclose = function (ev) {
            msgBox.append('<div class="system_msg">Connection Closed</div>');
        };

        //Message send button
        $('#send-message').click(function () {
            send_message();
        });

        //User hits enter key
        $("#message").on("keydown", function (event) {
            if (event.which == 13) {
                send_message();
            }
        });

        //Send message
        function send_message() {
            var message_input = $('#message'); //user message text
            var name_input = $('#name'); //user name

            if (message_input.val() == "") { //emtpy message?
                alert("Enter Some message Please!");
                return;
            }

            //prepare json data
            var msg = {
                type: 'usermsg',
                addr: 2,
                from: userId,
                room: null,
                text: message_input.val()
            };

            $.ajax({
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                url: '/messages/send',
                type: 'POST',
                data: msg,
                success: function (res) {
                    //convert and send data to server
                    msg.username = res.username;
                    msg.created_at = res.created_at;
                    websocket.send(JSON.stringify(msg));
                    message_input.val(''); //reset message input
                }
            });
        }
    </script>
@endsection