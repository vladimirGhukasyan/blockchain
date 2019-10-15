<?php
namespace App;


class Webs
{

    public function websocket_open($host='',$port=80,$headers='',&$error_string='',$timeout=10,$ssl=false){
        // Generate a key (to convince server that the update is not random)
        // The key is for the server to prove it i websocket aware. (We know it is)
        $key=base64_encode(openssl_random_pseudo_bytes(16));
        $header = "GET / HTTP/1.1\r\n"
            ."Host: $host\r\n"
            ."pragma: no-cache\r\n"
            ."Upgrade: WebSocket\r\n"
            ."Connection: Upgrade\r\n"
            ."Sec-WebSocket-Key: $key\r\n"
            ."Sec-WebSocket-Version: 13\r\n";
        // Add extra headers
        if(!empty($headers)) foreach($headers as $h) $header.=$h."\r\n";
        // Add end of header marker
        $header.="\r\n";
        // Connect to server
        $host = $host ? $host : "127.0.0.1";
        $port = $port <1 ? 80 : $port;
        $address = ($ssl ? 'ssl://' : '') . $host . ':' . $port;
        $sp = stream_socket_client($address, $errno, $errstr, $timeout);
        if(!$sp){
            $error_string = "Unable to connect to websocket server: $errstr ($errno)";
            return false;
        }
        // Set timeouts
        stream_set_timeout($sp,$timeout);
        //Request upgrade to websocket
        $rc = fwrite($sp,$header);
        if(!$rc){
            $error_string
                = "Unable to send upgrade header to websocket server: $errstr ($errno)";
            return false;
        }
        // Read response into an assotiative array of headers. Fails if upgrade failes.
        $reaponse_header=fread($sp, 1024);
        // status code 101 indicates that the WebSocket handshake has completed.
        if(!strpos($reaponse_header," 101 ")
            || !strpos($reaponse_header,'Sec-WebSocket-Accept: ')){
            $error_string = "Server did not accept to upgrade connection to websocket."
                .$reaponse_header. E_USER_ERROR;
            return false;
        }
        // The key we send is returned, concatenate with "258EAFA5-E914-47DA-95CA-
        // C5AB0DC85B11" and then base64-encoded. one can verify if one feels the need...
        return $sp;
    }
    /*============================================================================*\
      Write to websocket
      int websocket_write(resource $handle, string $data ,[boolean $final])
      Write a chunk of data through the websocket, using hybi10 frame encoding
      handle
        the resource handle returned by websocket_open, if successful
      data
        Data to transport to server
      final (optional)
        indicate if this block is the final data block of this request. Default true
    \*============================================================================*/
    public function websocket_write($sp,$data,$final=true){
        // Assamble header: FINal 0x80 | Opcode 0x02
        $header=chr(($final?0x80:0) | 0x02); // 0x02 binary
        // Mask 0x80 | payload length (0-125)
        if(strlen($data)<126) $header.=chr(0x80 | strlen($data));
        elseif (strlen($data)<0xFFFF) $header.=chr(0x80 | 126) . pack("n",strlen($data));
        else $header.=chr(0x80 | 127) . pack("N",0) . pack("N",strlen($data));
        // Add mask
        $mask=pack("N",rand(1,0x7FFFFFFF));
        $header.=$mask;
        // Mask application data.
        for($i = 0; $i < strlen($data); $i++)
            $data[$i]=chr(ord($data[$i]) ^ ord($mask[$i % 4]));
        return fwrite($sp,$header.$data);
    }
    /*============================================================================*\
      Read from websocket
      string websocket_read(resource $handle [,string &error_string])
      read a chunk of data from the server, using hybi10 frame encoding
      handle
        the resource handle returned by websocket_open, if successful
      error_string (optional)
        A referenced variable to store error messages, i any
      Read
      Note:
        - This implementation waits for the final chunk of data, before returning.
        - Reading data while handling/ignoring other kind of packages
     \*============================================================================*/
    public function websocket_read($sp,&$error_string=NULL){
        $data="";
        do{
            // Read header
            $header=fread($sp,2);
            if(!$header){
                $error_string = "Reading header from websocket failed.";
                return false;
            }
            $opcode = ord($header[0]) & 0x0F;
            $final = ord($header[0]) & 0x80;
            $masked = ord($header[1]) & 0x80;
            $payload_len = ord($header[1]) & 0x7F;
            // Get payload length extensions
            $ext_len = 0;
            if($payload_len >= 0x7E){
                $ext_len = 2;
                if($payload_len == 0x7F) $ext_len = 8;
                $header=fread($sp,$ext_len);
                if(!$header){
                    $error_string = "Reading header extension from websocket failed.";
                    return false;
                }
                // Set extented paylod length
                $payload_len= 0;
                for($i=0;$i<$ext_len;$i++)
                    $payload_len += ord($header[$i]) << ($ext_len-$i-1)*8;
            }
            // Get Mask key
            if($masked){
                $mask=fread($sp,4);
                if(!$mask){
                    $error_string = "Reading header mask from websocket failed.";
                    return false;
                }
            }
            // Get payload
            $frame_data='';
            do{
                $frame= fread($sp,$payload_len);
                if(!$frame){
                    $error_string = "Reading from websocket failed.";
                    return false;
                }
                $payload_len -= strlen($frame);
                $frame_data.=$frame;
            }while($payload_len>0);
            // Handle ping requests (sort of) send pong and continue to read
            if($opcode == 9){
                // Assamble header: FINal 0x80 | Opcode 0x0A + Mask on 0x80 with zero payload
                fwrite($sp,chr(0x8A) . chr(0x80) . pack("N", rand(1,0x7FFFFFFF)));
                continue;
                // Close
            } elseif($opcode == 8){
                fclose($sp);
                // 0 = continuation frame, 1 = text frame, 2 = binary frame
            }elseif($opcode < 3){
                // Unmask data
                $data_len=strlen($frame_data);
                if($masked)
                    for ($i = 0; $i < $data_len; $i++)
                        $data.= $frame_data[$i] ^ $mask[$i % 4];
                else
                    $data.= $frame_data;
            }else
                continue;
        }while(!$final);
        return $data;
    }
}