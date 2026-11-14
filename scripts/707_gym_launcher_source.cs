using System;
using System.Diagnostics;
using System.IO;
using System.Net.Sockets;
using System.Reflection;
using System.Threading;
using System.Windows.Forms;

namespace Gym707Launcher
{
    static class Program
    {
        private const string AppUrl = "http://localhost/707_GYM/";

        [STAThread]
        static void Main(string[] args)
        {
            try
            {
                string xamppPath = DetectXamppPath();
                bool startedAny = false;

                if (!string.IsNullOrEmpty(xamppPath))
                {
                    if (EnsureApacheRunning(xamppPath)) startedAny = true;
                    if (EnsureMysqlRunning(xamppPath)) startedAny = true;
                }

                // If Apache was not already running, wait up to 3 seconds for port 80
                if (startedAny)
                {
                    WaitForPort(80, 3000);
                }

                // Launch in user's default browser
                Process.Start(new ProcessStartInfo
                {
                    FileName = AppUrl,
                    UseShellExecute = true
                });
            }
            catch (Exception ex)
            {
                try
                {
                    Process.Start(new ProcessStartInfo
                    {
                        FileName = AppUrl,
                        UseShellExecute = true
                    });
                }
                catch
                {
                    MessageBox.Show(
                        "Hindi ma-open ang 707 GYM:\n" + ex.Message,
                        "707 GYM Launcher",
                        MessageBoxButtons.OK,
                        MessageBoxIcon.Error);
                }
            }
        }

        static bool IsProcessRunning(string name)
        {
            try
            {
                return Process.GetProcessesByName(name).Length > 0;
            }
            catch
            {
                return false;
            }
        }

        static bool EnsureApacheRunning(string xamppPath)
        {
            if (IsProcessRunning("httpd")) return false;

            string apacheDir = Path.Combine(xamppPath, "apache");
            string binDir = Path.Combine(apacheDir, "bin");
            string httpdExe = Path.Combine(binDir, "httpd.exe");
            string logsDir = Path.Combine(apacheDir, "logs");
            string pidFile = Path.Combine(logsDir, "httpd.pid");

            if (File.Exists(pidFile))
            {
                try { File.Delete(pidFile); } catch { }
            }

            if (File.Exists(httpdExe))
            {
                LaunchHidden(httpdExe, "", binDir);
                return true;
            }
            return false;
        }

        static bool EnsureMysqlRunning(string xamppPath)
        {
            if (IsProcessRunning("mysqld") || IsProcessRunning("mariadbd")) return false;

            string mysqlDir = Path.Combine(xamppPath, "mysql");
            string binDir = Path.Combine(mysqlDir, "bin");
            string dataDir = Path.Combine(mysqlDir, "data");

            if (Directory.Exists(dataDir))
            {
                try
                {
                    foreach (string pid in Directory.GetFiles(dataDir, "*.pid"))
                    {
                        try { File.Delete(pid); } catch { }
                    }
                }
                catch { }
            }

            string mysqldExe = Path.Combine(binDir, "mysqld.exe");
            if (!File.Exists(mysqldExe))
            {
                mysqldExe = Path.Combine(binDir, "mariadbd.exe");
            }

            if (File.Exists(mysqldExe))
            {
                string myIni = Path.Combine(binDir, "my.ini");
                string myArgs = File.Exists(myIni) ? "--defaults-file=\"" + myIni + "\"" : "--standalone";
                LaunchHidden(mysqldExe, myArgs, binDir);
                return true;
            }
            return false;
        }

        static void LaunchHidden(string fileName, string arguments, string workingDir)
        {
            try
            {
                ProcessStartInfo psi = new ProcessStartInfo
                {
                    FileName = fileName,
                    Arguments = arguments,
                    WorkingDirectory = workingDir,
                    UseShellExecute = false,
                    CreateNoWindow = true,
                    WindowStyle = ProcessWindowStyle.Hidden
                };
                Process.Start(psi);
            }
            catch { }
        }

        static void WaitForPort(int port, int timeoutMs)
        {
            int elapsed = 0;
            const int step = 150;
            while (elapsed < timeoutMs)
            {
                try
                {
                    using (TcpClient client = new TcpClient())
                    {
                        IAsyncResult result = client.BeginConnect("127.0.0.1", port, null, null);
                        bool success = result.AsyncWaitHandle.WaitOne(step);
                        if (success && client.Connected)
                        {
                            client.EndConnect(result);
                            return;
                        }
                    }
                }
                catch
                {
                    // Service starting up...
                }
                elapsed += step;
                Thread.Sleep(50);
            }
        }

        static string DetectXamppPath()
        {
            string[] candidates = new string[]
            {
                @"C:\xampp",
                @"D:\xampp",
                @"E:\xampp",
                Path.GetDirectoryName(Assembly.GetExecutingAssembly().Location)
            };

            foreach (string path in candidates)
            {
                if (!string.IsNullOrEmpty(path) && Directory.Exists(path) && Directory.Exists(Path.Combine(path, "apache")))
                {
                    return path;
                }
            }

            return null;
        }
    }
}
